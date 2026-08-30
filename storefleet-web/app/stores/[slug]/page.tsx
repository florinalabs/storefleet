import type { Metadata } from "next";
import Link from "next/link";
import { notFound } from "next/navigation";

import StoreBranchSelector from "@/components/store-branch-selector";
import {
  getStoreBySlug,
  getStoreProducts,
  type PublicProduct,
} from "@/lib/storefleet-api";


/*
|--------------------------------------------------------------------------
| Metadata
|--------------------------------------------------------------------------
*/

export async function generateMetadata({
  params,
}: {
  params: Promise<{
    slug: string;
  }>;
}): Promise<Metadata> {
  const { slug } =
    await params;

  const response =
    await getStoreBySlug(
      slug
    );

  if (!response) {
    return {
      title: "Store Not Found",
    };
  }

  const {
    store,
  } = response;

  return {
    title: store.name,
    description:
      stripHtml(
        store.description
      ) ||
      `Shop ${store.name} on StoreFleet.`,
  };
}


/*
|--------------------------------------------------------------------------
| Store Page
|--------------------------------------------------------------------------
*/

export default async function StorePage({
  params,
  searchParams,
}: {
  params: Promise<{
    slug: string;
  }>;

  searchParams: Promise<{
    branch?: string | string[];
  }>;
}) {
  const {
    slug,
  } =
    await params;


  const query =
    await searchParams;


  const response =
    await getStoreBySlug(
      slug
    );


  if (!response) {
    notFound();
  }


  const {
    store,
    branches,
  } = response;


  const openBranchCount =
    branches.filter(
      (branch) =>
        branch.is_open_now
    ).length;


  const primaryBranch =
    branches.find(
      (branch) =>
        branch.id ===
        store.primary_branch_id
    ) ??
    branches[0] ??
    null;


  const rawBranch =
    Array.isArray(
      query.branch
    )
      ? query.branch[0]
      : query.branch;


  const requestedBranchId =
    rawBranch
      ? Number.parseInt(
          rawBranch,
          10
        )
      : 0;


  /*
  |--------------------------------------------------------------------------
  | Branch Selection
  |--------------------------------------------------------------------------
  |
  | One active branch:
  | - automatically use it
  | - hide branch chooser
  | - load products immediately
  |
  | Multiple active branches:
  | - use ?branch=ID when selected
  | - otherwise use primary branch
  |
  */

  const selectedBranch =
    branches.length === 1
      ? branches[0]
      : branches.find(
          (branch) =>
            branch.id ===
            requestedBranchId
        ) ??
        primaryBranch;


  const showBranchChooser =
    branches.length > 1;


  const productsResponse =
    selectedBranch
      ? await getStoreProducts(
          store.slug,
          selectedBranch.id
        )
      : null;


  const storeProducts =
    productsResponse
      ?.products ??
    [];


  return (
    <main>

      {/* Store Hero */}

      <section className="bg-zinc-950 text-white">

        {store.banner_url && (
          <div className="h-48 w-full overflow-hidden border-b border-white/10 sm:h-64 lg:h-72">
            <img
              src={store.banner_url}
              alt=""
              className="h-full w-full object-cover opacity-80"
            />
          </div>
        )}


        <div className="mx-auto w-full max-w-7xl px-5 py-8 sm:px-6 lg:px-8">

          <div className="mb-8 flex flex-wrap items-center gap-2 text-sm text-zinc-400">

            <Link
              href="/"
              className="hover:text-white"
            >
              Home
            </Link>

            <span>/</span>

            <Link
              href="/stores"
              className="hover:text-white"
            >
              Stores
            </Link>

            <span>/</span>

            <span className="text-white">
              {store.name}
            </span>

          </div>


          <div className="grid gap-8 lg:grid-cols-[180px_1fr] lg:items-center">

            <div className="aspect-square overflow-hidden rounded-3xl border border-white/10 bg-zinc-800">

              {store.logo_url ? (
                <img
                  src={store.logo_url}
                  alt={store.name}
                  className="h-full w-full object-cover"
                />
              ) : (
                <div className="flex h-full items-center justify-center text-5xl font-black text-zinc-500">
                  {store.name
                    .charAt(0)
                    .toUpperCase()}
                </div>
              )}

            </div>


            <div>

              <span className="inline-flex rounded-full bg-white/10 px-3 py-1.5 text-xs font-bold text-zinc-200">
                StoreFleet Merchant
              </span>


              <h1 className="mt-5 text-4xl font-black leading-none tracking-[-0.055em] sm:text-5xl lg:text-6xl">
                {store.name}
              </h1>


              {store.description ? (
                <div
                  className="mt-6 max-w-3xl text-base leading-7 text-zinc-300 sm:text-lg"
                  dangerouslySetInnerHTML={{
                    __html:
                      store.description,
                  }}
                />
              ) : (
                <p className="mt-6 max-w-3xl text-base leading-7 text-zinc-300 sm:text-lg">
                  Shop products from{" "}
                  {store.name} on StoreFleet.
                </p>
              )}


              {store.address.formatted && (
                <div className="mt-6 flex items-start gap-2 text-sm text-zinc-300">

                  <LocationIcon />

                  <span>
                    {
                      store.address
                        .formatted
                    }
                  </span>

                </div>
              )}


              <div className="mt-8 grid max-w-3xl grid-cols-2 gap-px overflow-hidden rounded-2xl bg-white/10 sm:grid-cols-4">

                <StoreStat
                  value={String(
                    store.branch_count
                  )}
                  label={
                    store.branch_count ===
                    1
                      ? "Branch"
                      : "Branches"
                  }
                />

                <StoreStat
                  value={String(
                    openBranchCount
                  )}
                  label="Open now"
                />

                <StoreStat
                  value={formatNumber(
                    storeProducts.length
                  )}
                  label="Products"
                />

                <StoreStat
                  value={
                    selectedBranch
                      ? selectedBranch.name
                      : "—"
                  }
                  label="Selected branch"
                />

              </div>

            </div>

          </div>

        </div>

      </section>


      {/* Store Navigation */}

      <section className="border-b border-zinc-200 bg-white">

        <div className="mx-auto flex w-full max-w-7xl gap-8 overflow-x-auto px-5 sm:px-6 lg:px-8">

          {showBranchChooser && (
            <a
              href="#branches"
              className="border-b-2 border-violet-600 py-5 text-sm font-bold text-violet-600"
            >
              Branches
            </a>
          )}

          <a
            href="#products"
            className={`py-5 text-sm font-bold ${
              showBranchChooser
                ? "text-zinc-500 transition hover:text-zinc-950"
                : "border-b-2 border-violet-600 text-violet-600"
            }`}
          >
            Products
          </a>

          <a
            href="#about"
            className="py-5 text-sm font-semibold text-zinc-500 transition hover:text-zinc-950"
          >
            About
          </a>

        </div>

      </section>


      {/* Multiple Branches Only */}

      {showBranchChooser && (
        <section
          id="branches"
          className="scroll-mt-32 border-b border-zinc-200 bg-zinc-50 py-12 sm:py-16"
        >

          <div className="mx-auto w-full max-w-7xl px-5 sm:px-6 lg:px-8">

            <span className="text-xs font-black uppercase tracking-[0.15em] text-violet-600">
              Choose a location
            </span>

            <div className="mt-3 mb-8 flex flex-wrap items-end justify-between gap-5">

              <div>

                <h2 className="text-3xl font-black tracking-[-0.04em] sm:text-4xl">
                  Store branches
                </h2>

                <p className="mt-2 max-w-2xl text-sm leading-6 text-zinc-500">
                  Select a branch. StoreFleet will then load the products and stock for that location.
                </p>

              </div>

              <span className="text-sm text-zinc-500">
                {branches.length} active branches
              </span>

            </div>


            <StoreBranchSelector
              branches={branches}
              selectedBranchId={
                selectedBranch
                  ?.id ??
                null
              }
              storeSlug={
                store.slug
              }
            />

          </div>

        </section>
      )}


      {/* Products */}

      <section
        id="products"
        className="scroll-mt-32 py-12 sm:py-16"
      >

        <div className="mx-auto w-full max-w-7xl px-5 sm:px-6 lg:px-8">

          <div className="mb-8 flex flex-wrap items-end justify-between gap-6">

            <div>

              <span className="text-xs font-black uppercase tracking-[0.15em] text-violet-600">
                Store products
              </span>

              <h2 className="mt-3 text-3xl font-black tracking-[-0.04em] sm:text-4xl">
                Shop {store.name}
              </h2>

              {selectedBranch && (
                <p className="mt-2 text-sm text-zinc-500">
                  Available from{" "}
                  <strong className="text-zinc-700">
                    {selectedBranch.name}
                  </strong>
                </p>
              )}

            </div>


            <span className="text-sm text-zinc-500">
              {storeProducts.length}{" "}
              {storeProducts.length === 1
                ? "product"
                : "products"}
            </span>

          </div>


          {!selectedBranch ? (

            <div className="rounded-3xl border border-dashed border-zinc-300 py-20 text-center">

              <h3 className="text-xl font-black">
                No active branch.
              </h3>

              <p className="mt-2 text-zinc-500">
                Products are unavailable until this store has an active branch.
              </p>

            </div>

          ) : storeProducts.length > 0 ? (

            <div className="grid grid-cols-2 gap-3 sm:gap-5 md:grid-cols-3 lg:grid-cols-4">

              {storeProducts.map(
                (product) => (

                  <ProductCard
                    key={product.id}
                    product={product}
                  />

                )
              )}

            </div>

          ) : (

            <div className="rounded-3xl border border-dashed border-zinc-300 py-20 text-center">

              <h3 className="text-xl font-black">
                No products available.
              </h3>

              <p className="mt-2 text-zinc-500">
                There are no products available at{" "}
                {selectedBranch.name}.
              </p>

            </div>

          )}

        </div>

      </section>


      {/* About */}

      <section
        id="about"
        className="scroll-mt-32 border-t border-zinc-200 bg-zinc-50 py-16"
      >

        <div className="mx-auto grid w-full max-w-7xl gap-10 px-5 sm:px-6 lg:grid-cols-[1fr_0.7fr] lg:px-8">

          <div>

            <span className="text-xs font-black uppercase tracking-[0.15em] text-violet-600">
              About the merchant
            </span>

            <h2 className="mt-4 text-3xl font-black tracking-[-0.04em]">
              {store.name}
            </h2>

            {store.description ? (
              <div
                className="mt-5 max-w-3xl leading-8 text-zinc-600"
                dangerouslySetInnerHTML={{
                  __html:
                    store.description,
                }}
              />
            ) : (
              <p className="mt-5 max-w-3xl leading-8 text-zinc-600">
                {store.name} is available on StoreFleet with{" "}
                {store.branch_count} active{" "}
                {store.branch_count ===
                1
                  ? "branch"
                  : "branches"}.
              </p>
            )}

          </div>


          <div className="rounded-3xl border border-zinc-200 bg-white p-6">

            <h3 className="font-black">
              Store information
            </h3>

            <div className="mt-6 space-y-5">

              <InfoRow
                label="Store"
                value={store.name}
              />

              <InfoRow
                label="Address"
                value={
                  store.address
                    .formatted ||
                  "Not provided"
                }
              />

              <InfoRow
                label="Phone"
                value={
                  store.phone ||
                  "Not provided"
                }
              />

              <InfoRow
                label="Branches"
                value={`${store.branch_count} active`}
              />

              <InfoRow
                label="Products at selected branch"
                value={`${storeProducts.length} products`}
              />

            </div>

          </div>

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


  const category =
    product.categories[0]
      ?.name ??
    "Product";


  return (
    <article className="group overflow-hidden rounded-2xl border border-zinc-200 bg-white transition hover:-translate-y-1 hover:shadow-xl hover:shadow-zinc-950/5">

      <Link
        href={`/shop/${product.slug}`}
        className="block aspect-square overflow-hidden bg-zinc-100"
      >

        {product.image_url ? (
          <img
            src={product.image_url}
            alt={product.name}
            loading="lazy"
            className="h-full w-full object-cover transition duration-300 group-hover:scale-[1.04]"
          />
        ) : (
          <div className="flex h-full items-center justify-center px-4 text-center text-sm font-bold text-zinc-400">
            No product image
          </div>
        )}

      </Link>


      <div className="p-4">

        <span className="text-xs font-semibold text-zinc-400">
          {category}
        </span>


        <Link
          href={`/shop/${product.slug}`}
        >
          <h3 className="mt-1.5 line-clamp-2 min-h-[40px] text-sm font-bold leading-5 transition group-hover:text-violet-600 sm:text-base">
            {product.name}
          </h3>
        </Link>


        <div className="mt-4 flex flex-wrap items-baseline gap-2">

          <strong className="text-lg font-black text-violet-700">
            {formatPrice(
              product.price,
              product.currency
            )}
          </strong>

          {hasDiscount && (
            <span className="text-xs text-zinc-400 line-through">
              {formatPrice(
                product.regular_price,
                product.currency
              )}
            </span>
          )}

        </div>


        {product.stock_quantity !==
          null &&
          product.stock_quantity > 0 &&
          product.stock_quantity <=
            20 && (
            <div className="mt-3 text-xs font-semibold text-orange-600">
              Only{" "}
              {formatNumber(
                product.stock_quantity
              )}{" "}
              left
            </div>
          )}


        {!product.in_stock && (
          <div className="mt-3 text-xs font-semibold text-red-600">
            Out of stock
          </div>
        )}

      </div>

    </article>
  );
}


/*
|--------------------------------------------------------------------------
| Components
|--------------------------------------------------------------------------
*/

function StoreStat({
  value,
  label,
}: {
  value: string;
  label: string;
}) {
  return (
    <div className="bg-white/5 p-5">

      <strong className="block truncate text-xl font-black">
        {value}
      </strong>

      <span className="mt-1 block text-xs text-zinc-400">
        {label}
      </span>

    </div>
  );
}


function InfoRow({
  label,
  value,
}: {
  label: string;
  value: string;
}) {
  return (
    <div className="flex items-start justify-between gap-5 border-b border-zinc-100 pb-4 last:border-0 last:pb-0">

      <span className="text-sm text-zinc-500">
        {label}
      </span>

      <strong className="max-w-[70%] text-right text-sm">
        {value}
      </strong>

    </div>
  );
}


function LocationIcon() {
  return (
    <svg
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      strokeWidth="2"
      className="mt-0.5 h-4 w-4 shrink-0"
    >
      <path d="M20 10c0 5-8 12-8 12S4 15 4 10a8 8 0 1 1 16 0Z" />

      <circle
        cx="12"
        cy="10"
        r="2.5"
      />
    </svg>
  );
}


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


function stripHtml(
  value: string
) {
  return value.replace(
    /<[^>]*>/g,
    ""
  );
}
