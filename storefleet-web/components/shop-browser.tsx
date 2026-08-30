"use client";

import Link from "next/link";
import {
  useMemo,
  useState,
} from "react";

import type {
  PublicProduct,
} from "@/lib/storefleet-api";


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
    search,
    setSearch,
  ] = useState("");

  const [
    sort,
    setSort,
  ] = useState<
    "name" |
    "price-low" |
    "price-high" |
    "stock"
  >("name");


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


  const filteredProducts =
    useMemo(() => {
      let result =
        [...products];


      if (
        selectedCategory !==
        "All"
      ) {
        result =
          result.filter(
            (product) =>
              product.categories.some(
                (category) =>
                  category.name ===
                  selectedCategory
              )
          );
      }


      if (search.trim()) {
        const query =
          search
            .trim()
            .toLowerCase();

        result =
          result.filter(
            (product) => {
              const categoryText =
                product.categories
                  .map(
                    (category) =>
                      category.name
                  )
                  .join(" ");

              const branchText =
                product.branch
                  ?.name ??
                "";

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


      switch (sort) {
        case "price-low":
          result.sort(
            (a, b) =>
              a.price -
              b.price
          );
          break;

        case "price-high":
          result.sort(
            (a, b) =>
              b.price -
              a.price
          );
          break;

        case "stock":
          result.sort(
            (a, b) =>
              stockSortValue(b) -
              stockSortValue(a)
          );
          break;

        default:
          result.sort(
            (a, b) =>
              a.name.localeCompare(
                b.name
              )
          );
      }


      return result;
    }, [
      products,
      search,
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
                Discover live products from local StoreFleet merchants.
              </p>

            </div>


            <div className="text-sm text-zinc-500">
              {filteredProducts.length}{" "}
              {filteredProducts.length === 1
                ? "product"
                : "products"}
            </div>

          </div>

        </div>

      </section>


      {/* Search + Filters */}

      <section className="sticky top-[72px] z-40 border-b border-zinc-200 bg-white/95 backdrop-blur">

        <div className="mx-auto w-full max-w-7xl px-5 py-4 sm:px-6 lg:px-8">

          <div className="flex flex-col gap-4 lg:flex-row lg:items-center">

            <div className="relative flex-1">

              <svg
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                strokeWidth="2"
                className="absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-zinc-400"
              >
                <circle
                  cx="11"
                  cy="11"
                  r="8"
                />

                <path d="m21 21-4.35-4.35" />
              </svg>

              <input
                type="search"
                value={search}
                onChange={(event) =>
                  setSearch(
                    event.target.value
                  )
                }
                placeholder="Search products, stores or branches"
                className="min-h-12 w-full rounded-full border border-zinc-300 bg-white pl-12 pr-5 text-sm outline-none transition focus:border-violet-600 focus:ring-4 focus:ring-violet-600/10"
              />

            </div>


            <div className="flex gap-2 overflow-x-auto pb-1 lg:pb-0">

              {categories.map(
                (category) => {
                  const active =
                    selectedCategory ===
                    category;

                  return (
                    <button
                      key={category}
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


            <select
              value={sort}
              onChange={(event) =>
                setSort(
                  event.target
                    .value as
                    | "name"
                    | "price-low"
                    | "price-high"
                    | "stock"
                )
              }
              className="min-h-12 rounded-full border border-zinc-300 bg-white px-4 text-sm font-semibold outline-none focus:border-violet-600"
            >
              <option value="name">
                Product Name
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

        </div>

      </section>


      {/* Products */}

      <section className="py-10 sm:py-14">

        <div className="mx-auto w-full max-w-7xl px-5 sm:px-6 lg:px-8">

          {filteredProducts.length > 0 ? (

            <div className="grid grid-cols-2 gap-3 sm:gap-5 md:grid-cols-3 lg:grid-cols-4">

              {filteredProducts.map(
                (product) => (
                  <ProductCard
                    key={product.id}
                    product={product}
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
                Try another search or category.
              </p>

              <button
                type="button"
                onClick={() => {
                  setSearch("");
                  setSelectedCategory(
                    "All"
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
}: {
  product: PublicProduct;
}) {
  const hasDiscount =
    product.regular_price >
    product.price;


  const discount =
    hasDiscount &&
    product.regular_price > 0
      ? Math.round(
          ((product.regular_price -
            product.price) /
            product.regular_price) *
            100
        )
      : 0;


  const category =
    product.categories[0]
      ?.name ??
    "Product";


  return (
    <article className="group relative overflow-hidden rounded-2xl border border-zinc-200 bg-white transition duration-200 hover:-translate-y-1 hover:border-zinc-300 hover:shadow-xl hover:shadow-zinc-950/5">

      <Link
        href={`/shop/${product.slug}`}
        className="relative block aspect-square overflow-hidden bg-zinc-100"
      >

        {product.image_url ? (
          <img
            src={product.image_url}
            alt={product.name}
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
          href={`/shop/${product.slug}`}
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


        <div className="mt-4 border-t border-zinc-100 pt-4">

          <Link
            href={`/stores/${product.merchant.slug}`}
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

              {product.branch && (
                <span className="mt-0.5 block truncate text-[11px] text-zinc-400">
                  {product.branch.name}
                </span>
              )}

            </div>

          </Link>

        </div>


        {!product.in_stock ? (
          <div className="mt-3 text-xs font-semibold text-red-600">
            Out of stock
          </div>
        ) : (
          product.stock_quantity !==
            null &&
          product.stock_quantity <=
            20 && (
            <div className="mt-3 text-xs font-semibold text-orange-600">
              Only{" "}
              {formatNumber(
                product.stock_quantity
              )}{" "}
              left
            </div>
          )
        )}

      </div>

    </article>
  );
}


/*
|--------------------------------------------------------------------------
| Utilities
|--------------------------------------------------------------------------
*/

function formatPrice(
  value: number,
  currency: string
) {
  return new Intl.NumberFormat(
    "en-PH",
    {
      style: "currency",
      currency:
        currency ||
        "PHP",
      minimumFractionDigits: 0,
    }
  ).format(value);
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

      maximumFractionDigits: 1,
    }
  ).format(value);
}


function getInitials(
  value: string
) {
  return value
    .split(" ")
    .slice(0, 2)
    .map(
      (word) =>
        word.charAt(0)
    )
    .join("")
    .toUpperCase();
}


function stockSortValue(
  product: PublicProduct
) {
  if (!product.in_stock) {
    return -1;
  }

  if (
    product.stock_quantity ===
    null
  ) {
    return 0;
  }

  return product.stock_quantity;
}
