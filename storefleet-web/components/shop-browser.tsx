"use client";

import Link from "next/link";
import {
  useMemo,
  useState,
} from "react";

import type {
  PublicProduct,
  PublicProductBranch,
} from "@/lib/storefleet-api";


type SortOption =
  | "name"
  | "price-low"
  | "price-high"
  | "stock"
  | "nearest";


type CustomerLocation = {
  latitude: number;
  longitude: number;
};


export default function ShopBrowser({
  products,
}: {
  products: PublicProduct[];
}) {
  const [
    selectedCategory,
    setSelectedCategory,
  ] = useState("All");

  const [
    selectedBranchId,
    setSelectedBranchId,
  ] = useState<number | "all">(
    "all"
  );

  const [
    search,
    setSearch,
  ] = useState("");

  const [
    branchSearch,
    setBranchSearch,
  ] = useState("");

  const [
    sort,
    setSort,
  ] = useState<SortOption>(
    "name"
  );

  const [
    customerLocation,
    setCustomerLocation,
  ] = useState<CustomerLocation | null>(
    null
  );

  const [
    locationStatus,
    setLocationStatus,
  ] = useState<
    | "idle"
    | "loading"
    | "ready"
    | "error"
  >("idle");


  /*
  |--------------------------------------------------------------------------
  | Categories
  |--------------------------------------------------------------------------
  */

  const categories =
    useMemo(
      () => [
        "All",
        ...Array.from(
          new Set(
            products.flatMap(
              (product) =>
                product.categories.map(
                  (category) =>
                    category.name
                )
            )
          )
        ).sort(
          (a, b) =>
            a.localeCompare(b)
        ),
      ],
      [products]
    );


  /*
  |--------------------------------------------------------------------------
  | All Marketplace Branches
  |--------------------------------------------------------------------------
  */

  const branches =
    useMemo(() => {
      const branchMap =
        new Map<
          number,
          PublicProductBranch
        >();


      products.forEach(
        (product) => {
          const productBranches =
            product.branches ??
            (
              product.branch
                ? [
                    product.branch,
                  ]
                : []
            );


          productBranches.forEach(
            (branch) => {
              if (
                !branch.is_active
              ) {
                return;
              }


              if (
                !branchMap.has(
                  branch.id
                )
              ) {
                branchMap.set(
                  branch.id,
                  branch
                );
              }
            }
          );
        }
      );


      return Array.from(
        branchMap.values()
      ).sort(
        (a, b) =>
          a.name.localeCompare(
            b.name
          )
      );
    }, [
      products,
    ]);


  /*
  |--------------------------------------------------------------------------
  | Branch Search
  |--------------------------------------------------------------------------
  */

  const visibleBranches =
    useMemo(() => {
      const query =
        branchSearch
          .trim()
          .toLowerCase();


      if (!query) {
        return branches;
      }


      return branches.filter(
        (branch) =>
          [
            branch.name,
            branch.address,
          ]
            .join(" ")
            .toLowerCase()
            .includes(query)
      );
    }, [
      branches,
      branchSearch,
    ]);


  /*
  |--------------------------------------------------------------------------
  | Use Customer Location
  |--------------------------------------------------------------------------
  */

  function useCurrentLocation() {
    if (
      typeof navigator ===
        "undefined" ||
      !navigator.geolocation
    ) {
      setLocationStatus(
        "error"
      );

      return;
    }


    setLocationStatus(
      "loading"
    );


    navigator.geolocation.getCurrentPosition(
      (position) => {
        setCustomerLocation({
          latitude:
            position.coords
              .latitude,

          longitude:
            position.coords
              .longitude,
        });

        setLocationStatus(
          "ready"
        );

        setSelectedBranchId(
          "all"
        );

        setSort(
          "nearest"
        );
      },

      () => {
        setLocationStatus(
          "error"
        );
      },

      {
        enableHighAccuracy:
          true,

        timeout:
          10000,

        maximumAge:
          300000,
      }
    );
  }


  /*
  |--------------------------------------------------------------------------
  | Filter + Resolve Product Branch
  |--------------------------------------------------------------------------
  */

  const resolvedProducts =
    useMemo(() => {
      let result =
        products.map(
          (product) => {
            const productBranches =
              getProductBranches(
                product
              );


            const selectedBranch =
              selectedBranchId ===
              "all"
                ? null
                : productBranches.find(
                    (branch) =>
                      branch.id ===
                      selectedBranchId
                  ) ??
                  null;


            const nearestBranch =
              customerLocation
                ? getNearestBranch(
                    productBranches,
                    customerLocation
                  )
                : null;


            const defaultBranch =
              productBranches.find(
                (branch) =>
                  branch.id ===
                  product.branch_id
              ) ??
              productBranches.find(
                (branch) =>
                  branch.is_primary &&
                  branch.available &&
                  branch.in_stock
              ) ??
              productBranches.find(
                (branch) =>
                  branch.available &&
                  branch.in_stock
              ) ??
              productBranches[0] ??
              product.branch ??
              null;


            const resolvedBranch =
              selectedBranch ??
              nearestBranch ??
              defaultBranch;


            const distanceKm =
              customerLocation &&
              resolvedBranch
                ? getBranchDistanceKm(
                    resolvedBranch,
                    customerLocation
                  )
                : null;


            return {
              product,
              productBranches,
              resolvedBranch,
              distanceKm,
            };
          }
        );


      /*
      |--------------------------------------------------------------------------
      | Category
      |--------------------------------------------------------------------------
      */

      if (
        selectedCategory !==
        "All"
      ) {
        result =
          result.filter(
            ({
              product,
            }) =>
              product.categories.some(
                (category) =>
                  category.name ===
                  selectedCategory
              )
          );
      }


      /*
      |--------------------------------------------------------------------------
      | Explicit Branch Filter
      |--------------------------------------------------------------------------
      */

      if (
        selectedBranchId !==
        "all"
      ) {
        result =
          result.filter(
            ({
              productBranches,
            }) =>
              productBranches.some(
                (branch) =>
                  branch.id ===
                    selectedBranchId &&
                  branch.available &&
                  branch.in_stock
              )
          );
      }


      /*
      |--------------------------------------------------------------------------
      | Product / Store / Category / Branch / Address Search
      |--------------------------------------------------------------------------
      */

      if (
        search.trim()
      ) {
        const query =
          search
            .trim()
            .toLowerCase();


        result =
          result.filter(
            ({
              product,
              productBranches,
            }) => {
              const categoryText =
                product.categories
                  .map(
                    (category) =>
                      category.name
                  )
                  .join(" ");


              const branchText =
                productBranches
                  .map(
                    (branch) =>
                      `${branch.name} ${branch.address}`
                  )
                  .join(" ");


              return [
                product.name,
                product.merchant.name,
                categoryText,
                branchText,
              ]
                .join(" ")
                .toLowerCase()
                .includes(query);
            }
          );
      }


      /*
      |--------------------------------------------------------------------------
      | Sorting
      |--------------------------------------------------------------------------
      */

      switch (sort) {
        case "price-low":
          result.sort(
            (a, b) =>
              a.product.price -
              b.product.price
          );
          break;

        case "price-high":
          result.sort(
            (a, b) =>
              b.product.price -
              a.product.price
          );
          break;

        case "stock":
          result.sort(
            (a, b) =>
              branchStockSortValue(
                b.resolvedBranch
              ) -
              branchStockSortValue(
                a.resolvedBranch
              )
          );
          break;

        case "nearest":
          result.sort(
            (a, b) =>
              (
                a.distanceKm ??
                Number.POSITIVE_INFINITY
              ) -
              (
                b.distanceKm ??
                Number.POSITIVE_INFINITY
              )
          );
          break;

        default:
          result.sort(
            (a, b) =>
              a.product.name.localeCompare(
                b.product.name
              )
          );
      }


      return result;
    }, [
      products,
      customerLocation,
      search,
      selectedBranchId,
      selectedCategory,
      sort,
    ]);


  return (
    <main>

      {/* Page Header */}

      <section className="border-b border-zinc-200 bg-zinc-50">

        <div className="mx-auto w-full max-w-7xl px-5 py-14 sm:px-6 lg:px-8 lg:py-18">

          <span className="text-xs font-black uppercase tracking-[0.15em] text-violet-600">
            StoreFleet Marketplace
          </span>


          <div className="mt-4 flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">

            <div>

              <h1 className="text-4xl font-black tracking-[-0.055em] sm:text-5xl lg:text-6xl">
                Shop local.
              </h1>


              <p className="mt-4 max-w-2xl text-lg leading-8 text-zinc-600">
                Discover products from nearby StoreFleet merchants and choose the best fulfillment branch.
              </p>

            </div>


            <div className="text-sm text-zinc-500">
              {resolvedProducts.length}{" "}
              {resolvedProducts.length ===
              1
                ? "product"
                : "products"}
            </div>

          </div>

        </div>

      </section>


      {/* Search + Filters */}

      <section className="sticky top-[72px] z-40 border-b border-zinc-200 bg-white/95 backdrop-blur">

        <div className="mx-auto w-full max-w-7xl px-5 py-4 sm:px-6 lg:px-8">

          <div className="grid gap-3 lg:grid-cols-[minmax(260px,1fr)_minmax(220px,0.7fr)_auto_auto]">

            {/* Main Search */}

            <div className="relative">

              <SearchIcon />

              <input
                type="search"
                value={search}
                onChange={(
                  event
                ) =>
                  setSearch(
                    event.target.value
                  )
                }
                placeholder="Search products, stores, branches or addresses"
                className="min-h-12 w-full rounded-full border border-zinc-300 bg-white pl-12 pr-5 text-sm outline-none transition focus:border-violet-600 focus:ring-4 focus:ring-violet-600/10"
              />

            </div>


            {/* Branch / Address Search */}

            <div className="relative">

              <LocationIcon />

              <input
                type="search"
                value={
                  branchSearch
                }
                onChange={(
                  event
                ) =>
                  setBranchSearch(
                    event.target.value
                  )
                }
                placeholder="Find branch or address"
                className="min-h-12 w-full rounded-full border border-zinc-300 bg-white pl-12 pr-5 text-sm outline-none transition focus:border-violet-600 focus:ring-4 focus:ring-violet-600/10"
              />

            </div>


            {/* Use My Location */}

            <button
              type="button"
              onClick={
                useCurrentLocation
              }
              disabled={
                locationStatus ===
                "loading"
              }
              className="inline-flex min-h-12 items-center justify-center gap-2 rounded-full border border-zinc-300 bg-white px-5 text-sm font-bold transition hover:border-violet-600 hover:text-violet-700 disabled:cursor-wait disabled:opacity-60"
            >
              <LocationIcon
                staticPosition
              />

              {locationStatus ===
              "loading"
                ? "Locating..."
                : locationStatus ===
                    "ready"
                  ? "Location on"
                  : "Use my location"}
            </button>


            {/* Sort */}

            <select
              value={sort}
              onChange={(
                event
              ) =>
                setSort(
                  event.target
                    .value as SortOption
                )
              }
              className="min-h-12 rounded-full border border-zinc-300 bg-white px-4 text-sm font-semibold outline-none focus:border-violet-600"
            >
              <option value="name">
                Product Name
              </option>

              <option value="nearest">
                Nearest Branch
              </option>

              <option value="price-low">
                Price: Low to High
              </option>

              <option value="price-high">
                Price: High to Low
              </option>

              <option value="stock">
                Most Stock
              </option>
            </select>

          </div>


          {/* Location Message */}

          {locationStatus ===
            "error" && (
            <p className="mt-3 text-sm font-medium text-red-600">
              Location could not be read. You can still search or choose a branch manually.
            </p>
          )}


          {customerLocation && (
            <p className="mt-3 text-sm text-zinc-500">
              Products can now be sorted by the nearest in-stock branch.
            </p>
          )}


          {/* Category Filters */}

          <div className="mt-4 flex gap-2 overflow-x-auto pb-1">

            {categories.map(
              (category) => {
                const active =
                  selectedCategory ===
                  category;


                return (
                  <button
                    key={
                      category
                    }
                    type="button"
                    onClick={() =>
                      setSelectedCategory(
                        category
                      )
                    }
                    className={`shrink-0 rounded-full px-4 py-2.5 text-sm font-semibold transition ${
                      active
                        ? "bg-zinc-950 text-white"
                        : "border border-zinc-300 bg-white text-zinc-700 hover:border-zinc-950"
                    }`}
                  >
                    {category}
                  </button>
                );
              }
            )}

          </div>


          {/* Branch Filters */}

          <div className="mt-3 flex gap-2 overflow-x-auto pb-1">

            <button
              type="button"
              onClick={() =>
                setSelectedBranchId(
                  "all"
                )
              }
              className={`shrink-0 rounded-full px-4 py-2.5 text-sm font-semibold transition ${
                selectedBranchId ===
                "all"
                  ? "bg-violet-600 text-white"
                  : "border border-zinc-300 bg-white text-zinc-700 hover:border-violet-600 hover:text-violet-700"
              }`}
            >
              All branches
            </button>


            {visibleBranches.map(
              (branch) => {
                const active =
                  selectedBranchId ===
                  branch.id;


                return (
                  <button
                    key={
                      branch.id
                    }
                    type="button"
                    onClick={() =>
                      setSelectedBranchId(
                        branch.id
                      )
                    }
                    title={
                      branch.address
                    }
                    className={`shrink-0 rounded-full px-4 py-2.5 text-sm font-semibold transition ${
                      active
                        ? "bg-violet-600 text-white"
                        : "border border-zinc-300 bg-white text-zinc-700 hover:border-violet-600 hover:text-violet-700"
                    }`}
                  >
                    {branch.name}
                  </button>
                );
              }
            )}

          </div>

        </div>

      </section>


      {/* Products */}

      <section className="py-10 sm:py-14">

        <div className="mx-auto w-full max-w-7xl px-5 sm:px-6 lg:px-8">

          {resolvedProducts.length >
          0 ? (

            <div className="grid grid-cols-2 gap-3 sm:gap-5 md:grid-cols-3 lg:grid-cols-4">

              {resolvedProducts.map(
                ({
                  product,
                  productBranches,
                  resolvedBranch,
                  distanceKm,
                }) => (
                  <ProductCard
                    key={
                      product.id
                    }
                    product={
                      product
                    }
                    branches={
                      productBranches
                    }
                    resolvedBranch={
                      resolvedBranch
                    }
                    distanceKm={
                      distanceKm
                    }
                  />
                )
              )}

            </div>

          ) : (

            <div className="rounded-3xl border border-dashed border-zinc-300 px-6 py-20 text-center">

              <h2 className="text-2xl font-black">
                No products found.
              </h2>


              <p className="mt-3 text-zinc-500">
                Try another product, category, branch or address.
              </p>


              <button
                type="button"
                onClick={() => {
                  setSearch(
                    ""
                  );

                  setBranchSearch(
                    ""
                  );

                  setSelectedCategory(
                    "All"
                  );

                  setSelectedBranchId(
                    "all"
                  );

                  setSort(
                    "name"
                  );
                }}
                className="mt-6 rounded-full bg-zinc-950 px-6 py-3 text-sm font-bold text-white"
              >
                Clear filters
              </button>

            </div>

          )}

        </div>

      </section>

    </main>
  );
}


/*
|--------------------------------------------------------------------------
| Product Card
|--------------------------------------------------------------------------
*/

function ProductCard({
  product,
  branches,
  resolvedBranch,
  distanceKm,
}: {
  product: PublicProduct;

  branches: PublicProductBranch[];

  resolvedBranch:
    PublicProductBranch | null;

  distanceKm:
    number | null;
}) {
  const hasDiscount =
    product.regular_price >
    product.price;


  const discount =
    hasDiscount &&
    product.regular_price > 0
      ? Math.round(
          (
            (
              product.regular_price -
              product.price
            ) /
            product.regular_price
          ) *
            100
        )
      : 0;


  const category =
    product.categories[0]
      ?.name ??
    "Product";


  const href =
    resolvedBranch
      ? `/shop/${product.slug}?branch=${resolvedBranch.id}`
      : `/shop/${product.slug}`;


  const storeHref =
    resolvedBranch
      ? `/stores/${product.merchant.slug}?branch=${resolvedBranch.id}#products`
      : `/stores/${product.merchant.slug}`;


  const stockQuantity =
    resolvedBranch
      ?.stock_quantity ??
    product.stock_quantity;


  const inStock =
    resolvedBranch
      ? resolvedBranch.in_stock &&
        resolvedBranch.available
      : product.in_stock;


  return (
    <article className="group relative overflow-hidden rounded-2xl border border-zinc-200 bg-white transition duration-200 hover:-translate-y-1 hover:border-zinc-300 hover:shadow-xl hover:shadow-zinc-950/5">

      <Link
        href={href}
        className="relative block aspect-square overflow-hidden bg-zinc-100"
      >

        {product.image_url ? (
          <img
            src={
              product.image_url
            }
            alt={
              product.name
            }
            loading="lazy"
            className="h-full w-full object-cover transition duration-300 group-hover:scale-[1.04]"
          />
        ) : (
          <div className="flex h-full items-center justify-center px-5 text-center text-sm font-bold text-zinc-400">
            No product image
          </div>
        )}


        {hasDiscount && (
          <span className="absolute left-3 top-3 rounded-full bg-red-500 px-2.5 py-1 text-xs font-black text-white">
            -{discount}%
          </span>
        )}

      </Link>


      <div className="p-4 sm:p-5">

        <span className="text-xs font-semibold text-zinc-400">
          {category}
        </span>


        <Link
          href={href}
        >
          <h2 className="mt-1.5 line-clamp-2 min-h-[44px] text-sm font-bold leading-5 transition group-hover:text-violet-600 sm:text-base">
            {product.name}
          </h2>
        </Link>


        <div className="mt-4 flex flex-wrap items-baseline gap-2">

          <strong className="text-lg font-black tracking-tight text-violet-700 sm:text-xl">
            {formatPrice(
              product.price,
              product.currency
            )}
          </strong>


          {hasDiscount && (
            <span className="text-xs text-zinc-400 line-through sm:text-sm">
              {formatPrice(
                product.regular_price,
                product.currency
              )}
            </span>
          )}

        </div>


        {/* Merchant + Fulfillment Branch */}

        <div className="mt-4 border-t border-zinc-100 pt-4">

          <Link
            href={storeHref}
            className="flex min-w-0 items-center gap-2"
          >

            <div className="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-violet-100 text-[10px] font-black text-violet-700">
              {getInitials(
                product.merchant.name
              )}
            </div>


            <div className="min-w-0">

              <span className="block truncate text-xs font-semibold text-zinc-600">
                {product.merchant.name}
              </span>


              {resolvedBranch && (
                <span className="mt-0.5 block truncate text-[11px] font-medium text-violet-600">
                  {resolvedBranch.name}

                  {distanceKm !==
                    null &&
                    Number.isFinite(
                      distanceKm
                    ) &&
                    ` · ${formatDistance(
                      distanceKm
                    )}`}
                </span>
              )}

            </div>

          </Link>


          {resolvedBranch
            ?.address && (
            <p className="mt-2 line-clamp-2 text-[11px] leading-4 text-zinc-400">
              {resolvedBranch.address}
            </p>
          )}


          {branches.length >
            1 && (
            <p className="mt-2 text-[11px] font-medium text-zinc-500">
              Available at{" "}
              {
                branches.length
              }{" "}
              branches
            </p>
          )}

        </div>


        {/* Branch Stock */}

        {!inStock ? (
          <div className="mt-3 text-xs font-semibold text-red-600">
            Out of stock at{" "}
            {resolvedBranch
              ?.name ??
              "this branch"}
          </div>
        ) : stockQuantity !==
            null ? (
          <div
            className={`mt-3 text-xs font-semibold ${
              stockQuantity <=
              20
                ? "text-orange-600"
                : "text-emerald-700"
            }`}
          >
            {formatNumber(
              stockQuantity
            )}{" "}
            in stock at{" "}
            {resolvedBranch
              ?.name ??
              "this branch"}
          </div>
        ) : (
          <div className="mt-3 text-xs font-semibold text-emerald-700">
            In stock at{" "}
            {resolvedBranch
              ?.name ??
              "this branch"}
          </div>
        )}

      </div>

    </article>
  );
}


/*
|--------------------------------------------------------------------------
| Product Branch Helpers
|--------------------------------------------------------------------------
*/

function getProductBranches(
  product: PublicProduct
) {
  const branches =
    product.branches ??
    (
      product.branch
        ? [
            product.branch,
          ]
        : []
    );


  return branches.filter(
    (branch) =>
      branch.is_active
  );
}


function getNearestBranch(
  branches: PublicProductBranch[],
  customerLocation: CustomerLocation
) {
  const candidates =
    branches.filter(
      (branch) =>
        branch.available &&
        branch.in_stock &&
        hasCoordinates(
          branch
        )
    );


  if (
    candidates.length ===
    0
  ) {
    return null;
  }


  return candidates.reduce(
    (
      nearest,
      branch
    ) => {
      const nearestDistance =
        getBranchDistanceKm(
          nearest,
          customerLocation
        );


      const branchDistance =
        getBranchDistanceKm(
          branch,
          customerLocation
        );


      return branchDistance <
        nearestDistance
        ? branch
        : nearest;
    }
  );
}


function getBranchDistanceKm(
  branch: PublicProductBranch,
  customerLocation: CustomerLocation
) {
  if (
    !hasCoordinates(
      branch
    )
  ) {
    return Number.POSITIVE_INFINITY;
  }


  return haversineKm(
    customerLocation.latitude,
    customerLocation.longitude,
    branch.location.latitude,
    branch.location.longitude
  );
}


function hasCoordinates(
  branch: PublicProductBranch
): branch is PublicProductBranch & {
  location: {
    latitude: number;
    longitude: number;
  };
} {
  return (
    typeof branch.location
      ?.latitude ===
      "number" &&
    typeof branch.location
      ?.longitude ===
      "number"
  );
}


function branchStockSortValue(
  branch:
    PublicProductBranch | null
) {
  if (
    !branch ||
    !branch.available ||
    !branch.in_stock
  ) {
    return -1;
  }


  if (
    branch.stock_quantity ===
    null
  ) {
    return 0;
  }


  return branch.stock_quantity;
}


/*
|--------------------------------------------------------------------------
| Distance
|--------------------------------------------------------------------------
*/

function haversineKm(
  latitudeA: number,
  longitudeA: number,
  latitudeB: number,
  longitudeB: number
) {
  const earthRadiusKm =
    6371;


  const latitudeDelta =
    toRadians(
      latitudeB -
      latitudeA
    );


  const longitudeDelta =
    toRadians(
      longitudeB -
      longitudeA
    );


  const a =
    Math.sin(
      latitudeDelta / 2
    ) **
      2 +
    Math.cos(
      toRadians(
        latitudeA
      )
    ) *
      Math.cos(
        toRadians(
          latitudeB
        )
      ) *
      Math.sin(
        longitudeDelta / 2
      ) **
        2;


  const c =
    2 *
    Math.atan2(
      Math.sqrt(a),
      Math.sqrt(1 - a)
    );


  return (
    earthRadiusKm *
    c
  );
}


function toRadians(
  value: number
) {
  return (
    value *
    Math.PI
  ) / 180;
}


/*
|--------------------------------------------------------------------------
| Icons
|--------------------------------------------------------------------------
*/

function SearchIcon() {
  return (
    <svg
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      strokeWidth="2"
      className="absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-zinc-400"
      aria-hidden="true"
    >
      <circle
        cx="11"
        cy="11"
        r="8"
      />

      <path d="m21 21-4.35-4.35" />
    </svg>
  );
}


function LocationIcon({
  staticPosition = false,
}: {
  staticPosition?: boolean;
}) {
  return (
    <svg
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      strokeWidth="2"
      className={
        staticPosition
          ? "h-4 w-4"
          : "absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-zinc-400"
      }
      aria-hidden="true"
    >
      <path d="M12 21s6-5.33 6-11a6 6 0 1 0-12 0c0 5.67 6 11 6 11Z" />

      <circle
        cx="12"
        cy="10"
        r="2"
      />
    </svg>
  );
}


/*
|--------------------------------------------------------------------------
| Formatting
|--------------------------------------------------------------------------
*/

function formatPrice(
  value: number,
  currency: string
) {
  return new Intl.NumberFormat(
    "en-PH",
    {
      style:
        "currency",

      currency:
        currency ||
        "PHP",

      minimumFractionDigits:
        0,
    }
  ).format(
    value
  );
}


function formatNumber(
  value: number
) {
  return new Intl.NumberFormat(
    "en-PH",
    {
      notation:
        value >= 1000
          ? "compact"
          : "standard",

      maximumFractionDigits:
        1,
    }
  ).format(
    value
  );
}


function formatDistance(
  distanceKm: number
) {
  if (
    distanceKm <
    1
  ) {
    return `${Math.round(
      distanceKm *
        1000
    )} m`;
  }


  return `${distanceKm.toFixed(
    distanceKm < 10
      ? 1
      : 0
  )} km`;
}


function getInitials(
  value: string
) {
  return value
    .split(" ")
    .slice(
      0,
      2
    )
    .map(
      (word) =>
        word.charAt(
          0
        )
    )
    .join("")
    .toUpperCase();
  }