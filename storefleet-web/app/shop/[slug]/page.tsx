import type { Metadata } from "next";
import Link from "next/link";
import { notFound } from "next/navigation";

import ProductPurchase from "@/components/cart/product-purchase";


/*
|--------------------------------------------------------------------------
| Types
|--------------------------------------------------------------------------
*/

type ProductCategory = {
  id: number;
  name: string;
  slug: string;
};


type ProductMerchant = {
  id: number;
  name: string;
  slug: string;
};


type ProductBranch = {
  id: number;
  name: string;
  slug: string;
  is_primary: boolean;
  is_active: boolean;
  address: string;
};


type PublicProduct = {
  id: number;
  slug: string;
  name: string;
  type: string;

  short_description: string;
  description: string;

  sku: string;
  currency: string;

  price: number;
  regular_price: number;

  on_sale: boolean;
  campaign: unknown | null;

  image_url: string;
  images: string[];

  categories: ProductCategory[];

  merchant: ProductMerchant;

  branch_id: number | null;
  branch?: ProductBranch | null;

  available: boolean;
  in_stock: boolean;

  stock_quantity: number | null;

  stock_source:
    | "branch"
    | "woocommerce";
};


type ProductResponse = {
  success: true;

  generated_at: string;
  timezone: string;

  branch: ProductBranch | null;

  product: PublicProduct;
};


type ProductsResponse = {
  success: true;

  products: PublicProduct[];
};


/*
|--------------------------------------------------------------------------
| StoreFleet API
|--------------------------------------------------------------------------
*/

function getWordPressUrl() {
  return (
    process.env.WORDPRESS_URL ??
    process.env.WORDPRESS_BASE_URL ??
    "http://localhost:8080"
  ).replace(
    /\/+$/,
    ""
  );
}


function getInternalApiKey() {
  const key =
    process.env
      .STOREFLEET_INTERNAL_API_KEY;


  if (!key) {
    throw new Error(
      "STOREFLEET_INTERNAL_API_KEY is not configured."
    );
  }


  return key;
}


async function storefleetFetch(
  path: string
) {
  return fetch(
    `${getWordPressUrl()}${path}`,
    {
      headers: {
        "x-storefleet-key":
          getInternalApiKey(),
      },

      next: {
        revalidate: 60,
      },
    }
  );
}


/*
|--------------------------------------------------------------------------
| Get One Product By Slug + Branch
|--------------------------------------------------------------------------
*/

async function getProductBySlug(
  slug: string,
  branchId?: number
): Promise<ProductResponse | null> {
  const params =
    new URLSearchParams();


  if (
    branchId &&
    branchId > 0
  ) {
    params.set(
      "branch_id",
      String(branchId)
    );
  }


  const query =
    params.toString();


  const response =
    await storefleetFetch(
      `/wp-json/storefleet/v1/products/by-slug/${encodeURIComponent(
        slug
      )}${query ? `?${query}` : ""}`
    );


  if (response.status === 404) {
    return null;
  }


  if (!response.ok) {
    throw new Error(
      `StoreFleet product API failed with status ${response.status}.`
    );
  }


  return (
    await response.json()
  ) as ProductResponse;
}


/*
|--------------------------------------------------------------------------
| Related Products
|--------------------------------------------------------------------------
*/

async function getRelatedProducts(
  product: PublicProduct
) {
  try {
    const response =
      await storefleetFetch(
        "/wp-json/storefleet/v1/products?page=1&per_page=100"
      );


    if (!response.ok) {
      return [];
    }


    const payload =
      (await response.json()) as ProductsResponse;


    return payload.products
      .filter(
        (item) =>
          item.id !==
            product.id &&
          (
            item.merchant.id ===
              product.merchant.id ||
            productsShareCategory(
              product,
              item
            )
          )
      )
      .slice(
        0,
        4
      );
  } catch {
    return [];
  }
}


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
  const {
    slug,
  } =
    await params;


  const response =
    await getProductBySlug(
      slug
    );


  if (!response) {
    return {
      title: "Product Not Found",
    };
  }


  const {
    product,
  } =
    response;


  const description =
    stripHtml(
      product.short_description
    ) ||
    `${product.name} from ${product.merchant.name} on StoreFleet.`;


  return {
    title:
      product.name,

    description,
  };
}


/*
|--------------------------------------------------------------------------
| Product Page
|--------------------------------------------------------------------------
*/

export default async function ProductPage({
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


  const rawBranch =
    Array.isArray(
      query.branch
    )
      ? query.branch[0]
      : query.branch;


  const parsedBranchId =
    rawBranch
      ? Number.parseInt(
          rawBranch,
          10
        )
      : undefined;


  const branchId =
    parsedBranchId &&
    Number.isInteger(
      parsedBranchId
    ) &&
    parsedBranchId > 0
      ? parsedBranchId
      : undefined;


  /*
  |--------------------------------------------------------------------------
  | Live Product
  |--------------------------------------------------------------------------
  |
  | /shop/product-slug?branch=2
  |
  | -> StoreFleet Product API
  | -> WooCommerce product
  | -> merchant
  | -> selected branch
  | -> branch availability
  | -> branch stock
  |
  */

  const response =
    await getProductBySlug(
      slug,
      branchId
    );


  if (!response) {
    notFound();
  }


  const {
    product,
    branch,
  } =
    response;


  const category =
    product.categories[0]
      ?.name ??
    "Product";


  const relatedProducts =
    await getRelatedProducts(
      product
    );


  /*
  |--------------------------------------------------------------------------
  | Discount
  |--------------------------------------------------------------------------
  */

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


  /*
  |--------------------------------------------------------------------------
  | Existing ProductPurchase Compatibility
  |--------------------------------------------------------------------------
  |
  | ProductPurchase currently expects a numeric stock value.
  | Branch-aware cart data will be added to that component in the next patch.
  |
  */

  const purchaseStock =
    product.in_stock
      ? product.stock_quantity !==
        null
        ? Math.max(
            0,
            Math.floor(
              product.stock_quantity
            )
          )
        : 100
      : 0;


  const storeUrl =
    branch
      ? `/stores/${product.merchant.slug}?branch=${branch.id}#products`
      : `/stores/${product.merchant.slug}`;


  return (
    <main>

      {/* Breadcrumb */}

      <section className="border-b border-zinc-200 bg-white">

        <div className="mx-auto flex w-full max-w-7xl flex-wrap items-center gap-2 px-5 py-5 text-sm text-zinc-500 sm:px-6 lg:px-8">

          <Link
            href="/"
            className="transition hover:text-violet-600"
          >
            Home
          </Link>

          <span>/</span>

          <Link
            href="/shop"
            className="transition hover:text-violet-600"
          >
            Shop
          </Link>

          <span>/</span>

          <span className="text-zinc-400">
            {category}
          </span>

          <span>/</span>

          <span className="max-w-[260px] truncate font-semibold text-zinc-950">
            {product.name}
          </span>

        </div>

      </section>


      {/* Main Product */}

      <section className="py-10 sm:py-14 lg:py-16">

        <div className="mx-auto grid w-full max-w-7xl gap-10 px-5 sm:px-6 lg:grid-cols-[1fr_1fr] lg:gap-16 lg:px-8">

          {/* Product Image */}

          <div>

            <div className="sticky top-28">

              <div className="relative aspect-square overflow-hidden rounded-3xl bg-zinc-100">

                {product.image_url ? (
                  <img
                    src={
                      product.image_url
                    }
                    alt={
                      product.name
                    }
                    className="h-full w-full object-cover"
                  />
                ) : (
                  <div className="flex h-full items-center justify-center px-6 text-center font-semibold text-zinc-400">
                    No product image
                  </div>
                )}


                {hasDiscount && (
                  <span className="absolute left-5 top-5 rounded-full bg-red-500 px-3 py-1.5 text-sm font-black text-white shadow-sm">
                    -{discount}%
                  </span>
                )}

              </div>

            </div>

          </div>


          {/* Product Information */}

          <div className="lg:py-3">

            {/* Category */}

            <Link
              href="/shop"
              className="text-xs font-black uppercase tracking-[0.15em] text-violet-600"
            >
              {category}
            </Link>


            {/* Name */}

            <h1 className="mt-4 text-4xl font-black leading-[1.02] tracking-[-0.055em] sm:text-5xl lg:text-6xl">
              {product.name}
            </h1>


            {/* Merchant / Branch Context */}

            <div className="mt-6 flex flex-wrap items-center gap-2 text-sm">

              <Link
                href={storeUrl}
                className="font-bold text-zinc-700 transition hover:text-violet-600"
              >
                {product.merchant.name}
              </Link>


              {branch && (
                <>
                  <span className="text-zinc-300">
                    /
                  </span>

                  <span className="rounded-full bg-violet-100 px-3 py-1.5 font-bold text-violet-700">
                    {branch.name}
                  </span>

                  {branch.is_primary && (
                    <span className="rounded-full bg-zinc-100 px-3 py-1.5 text-xs font-bold text-zinc-500">
                      Primary branch
                    </span>
                  )}
                </>
              )}

            </div>


            {/* Price */}

            <div className="mt-8">

              <div className="flex flex-wrap items-baseline gap-3">

                <strong className="text-4xl font-black tracking-[-0.04em] text-violet-700">
                  {formatPrice(
                    product.price,
                    product.currency
                  )}
                </strong>


                {hasDiscount && (
                  <span className="text-lg text-zinc-400 line-through">
                    {formatPrice(
                      product.regular_price,
                      product.currency
                    )}
                  </span>
                )}

              </div>


              {hasDiscount && (
                <div className="mt-2 text-sm font-semibold text-emerald-700">
                  You save{" "}
                  {formatPrice(
                    product.regular_price -
                      product.price,
                    product.currency
                  )}
                </div>
              )}

            </div>


            {/* Short Description */}

            <div className="mt-8 border-t border-zinc-200 pt-8">

              {product.short_description ? (
                <div
                  className="max-w-2xl leading-8 text-zinc-600"
                  dangerouslySetInnerHTML={{
                    __html:
                      product.short_description,
                  }}
                />
              ) : (
                <p className="max-w-2xl leading-8 text-zinc-600">
                  {product.name} from{" "}
                  <strong className="font-semibold text-zinc-950">
                    {product.merchant.name}
                  </strong>
                  .
                </p>
              )}

            </div>


            {/* Stock */}

            <div className="mt-6">

              {!product.in_stock ? (

                <div className="flex items-center gap-2 text-sm">

                  <span className="h-2.5 w-2.5 rounded-full bg-red-500" />

                  <span className="font-bold text-red-700">
                    Out of stock
                  </span>

                </div>

              ) : product.stock_quantity !==
                  null &&
                product.stock_quantity <=
                  20 ? (

                <div className="flex items-center gap-2 text-sm">

                  <span className="h-2.5 w-2.5 rounded-full bg-orange-500" />

                  <span className="font-bold text-orange-700">
                    Only{" "}
                    {formatNumber(
                      product.stock_quantity
                    )}{" "}
                    left
                  </span>

                </div>

              ) : (

                <div className="flex items-center gap-2 text-sm">

                  <span className="h-2.5 w-2.5 rounded-full bg-emerald-500" />

                  <span className="font-bold text-emerald-700">
                    In stock
                  </span>


                  {product.stock_quantity !==
                    null && (
                    <>
                      <span className="text-zinc-400">
                        ·
                      </span>

                      <span className="text-zinc-500">
                        {formatNumber(
                          product.stock_quantity
                        )}{" "}
                        available
                      </span>
                    </>
                  )}

                </div>

              )}

            </div>


            {/* Stock Source */}

            {branch && (
              <p className="mt-2 text-xs text-zinc-400">
                Availability for{" "}
                {branch.name}
              </p>
            )}


            {/* Purchase Controls */}

            <ProductPurchase
              product={{
                id:
                  product.id,

                slug:
                  product.slug,

                name:
                  product.name,

                price:
                  product.price,

                regularPrice:
                  product.regular_price,

                image:
                  product.image_url,

                stock:
                  purchaseStock,

                store: {
                  id:
                    product.merchant.id,

                  name:
                    product.merchant.name,

                  slug:
                    product.merchant.slug,

                  verified:
                    false,
                },
              }}
            />


            {/* Merchant */}

            <div className="mt-8 rounded-3xl border border-zinc-200 bg-white p-5 sm:p-6">

              <span className="text-xs font-bold uppercase tracking-[0.12em] text-zinc-400">
                Sold by
              </span>


              <div className="mt-4 flex flex-col gap-5 sm:flex-row sm:items-center sm:justify-between">

                <Link
                  href={storeUrl}
                  className="flex min-w-0 items-center gap-3"
                >

                  <div className="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-violet-100 text-sm font-black text-violet-700">
                    {getInitials(
                      product.merchant.name
                    )}
                  </div>


                  <div className="min-w-0">

                    <strong className="truncate text-base">
                      {product.merchant.name}
                    </strong>


                    <span className="mt-1 block text-sm text-zinc-500">
                      {branch
                        ? `Fulfilled by ${branch.name}`
                        : "StoreFleet Merchant"}
                    </span>

                  </div>

                </Link>


                <Link
                  href={storeUrl}
                  className="inline-flex min-h-10 shrink-0 items-center justify-center rounded-full border border-zinc-300 px-5 text-sm font-bold transition hover:border-violet-600 hover:text-violet-600"
                >
                  Visit Store
                </Link>

              </div>

            </div>

          </div>

        </div>

      </section>


      {/* Benefits */}

      <section className="border-y border-zinc-200 bg-zinc-50">

        <div className="mx-auto grid w-full max-w-7xl gap-px bg-zinc-200 sm:grid-cols-3">

          <ProductBenefit
            icon="◎"
            title="Local Merchant"
            text={`Sold by ${product.merchant.name} through StoreFleet.`}
          />

          <ProductBenefit
            icon="✓"
            title="Secure Payment"
            text="Complete your payment securely through StoreFleet checkout."
          />

          <ProductBenefit
            icon="→"
            title="Branch Fulfillment"
            text={
              branch
                ? `Prepared by ${branch.name} for local fulfillment.`
                : "Prepared by the merchant's active StoreFleet branch."
            }
          />

        </div>

      </section>


      {/* Product Information */}

      <section className="py-16">

        <div className="mx-auto grid w-full max-w-7xl gap-12 px-5 sm:px-6 lg:grid-cols-[1fr_0.65fr] lg:px-8">

          {/* Details */}

          <div>

            <span className="text-xs font-black uppercase tracking-[0.15em] text-violet-600">
              Product information
            </span>

            <h2 className="mt-4 text-3xl font-black tracking-[-0.04em] sm:text-4xl">
              About this product
            </h2>


            {product.description ? (
              <div
                className="mt-7 max-w-3xl leading-8 text-zinc-600"
                dangerouslySetInnerHTML={{
                  __html:
                    product.description,
                }}
              />
            ) : (
              <p className="mt-7 max-w-3xl leading-8 text-zinc-600">
                {product.name} is available from{" "}
                {product.merchant.name} on StoreFleet.
              </p>
            )}

          </div>


          {/* Product Details */}

          <div className="rounded-3xl border border-zinc-200 p-6">

            <h3 className="font-black">
              Product details
            </h3>


            <div className="mt-6 space-y-5">

              <InfoRow
                label="Category"
                value={category}
              />

              <InfoRow
                label="Store"
                value={
                  product.merchant.name
                }
              />

              <InfoRow
                label="Branch"
                value={
                  branch?.name ??
                  "Primary branch"
                }
              />


              {product.sku && (
                <InfoRow
                  label="SKU"
                  value={product.sku}
                />
              )}


              <InfoRow
                label="Availability"
                value={
                  product.in_stock
                    ? product.stock_quantity !==
                      null
                      ? `${formatNumber(
                          product.stock_quantity
                        )} in stock`
                      : "In stock"
                    : "Out of stock"
                }
              />

            </div>

          </div>

        </div>

      </section>


      {/* Related Products */}

      {relatedProducts.length >
        0 && (

        <section className="border-t border-zinc-200 py-16">

          <div className="mx-auto w-full max-w-7xl px-5 sm:px-6 lg:px-8">

            <div className="mb-8 flex items-end justify-between gap-6">

              <div>

                <span className="text-xs font-black uppercase tracking-[0.15em] text-violet-600">
                  Keep shopping
                </span>

                <h2 className="mt-3 text-3xl font-black tracking-[-0.04em] sm:text-4xl">
                  You may also like
                </h2>

              </div>


              <Link
                href="/shop"
                className="hidden text-sm font-bold text-violet-600 transition hover:text-violet-800 sm:block"
              >
                View all →
              </Link>

            </div>


            <div className="grid grid-cols-2 gap-3 sm:gap-5 md:grid-cols-3 lg:grid-cols-4">

              {relatedProducts.map(
                (item) => (
                  <RelatedProduct
                    key={
                      `${item.id}-${item.branch_id ?? "default"}`
                    }
                    product={item}
                  />
                )
              )}

            </div>

          </div>

        </section>

      )}

    </main>
  );
}


/*
|--------------------------------------------------------------------------
| Product Benefit
|--------------------------------------------------------------------------
*/

function ProductBenefit({
  icon,
  title,
  text,
}: {
  icon: string;
  title: string;
  text: string;
}) {
  return (
    <div className="bg-zinc-50 p-7 sm:p-8">

      <div className="flex h-10 w-10 items-center justify-center rounded-full bg-violet-100 font-black text-violet-700">
        {icon}
      </div>


      <h3 className="mt-5 font-black">
        {title}
      </h3>


      <p className="mt-2 text-sm leading-6 text-zinc-500">
        {text}
      </p>

    </div>
  );
}


/*
|--------------------------------------------------------------------------
| Product Information Row
|--------------------------------------------------------------------------
*/

function InfoRow({
  label,
  value,
}: {
  label: string;
  value: string;
}) {
  return (
    <div className="flex items-start justify-between gap-6 border-b border-zinc-100 pb-4 last:border-0 last:pb-0">

      <span className="text-sm text-zinc-500">
        {label}
      </span>


      <strong className="text-right text-sm">
        {value}
      </strong>

    </div>
  );
}


/*
|--------------------------------------------------------------------------
| Related Product
|--------------------------------------------------------------------------
*/

function RelatedProduct({
  product,
}: {
  product: PublicProduct;
}) {
  const category =
    product.categories[0]
      ?.name ??
    "Product";


  const branchId =
    product.branch_id ??
    product.branch?.id ??
    null;


  const href =
    branchId
      ? `/shop/${product.slug}?branch=${branchId}`
      : `/shop/${product.slug}`;


  const hasDiscount =
    product.regular_price >
    product.price;


  return (
    <article className="group overflow-hidden rounded-2xl border border-zinc-200 bg-white transition hover:-translate-y-1 hover:shadow-lg hover:shadow-zinc-950/5">

      <Link
        href={href}
        className="block aspect-square overflow-hidden bg-zinc-100"
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
          <div className="flex h-full items-center justify-center px-4 text-center text-sm font-semibold text-zinc-400">
            No product image
          </div>
        )}

      </Link>


      <div className="p-4">

        <span className="text-xs font-semibold text-zinc-400">
          {category}
        </span>


        <Link
          href={href}
        >
          <h3 className="mt-1.5 line-clamp-2 min-h-[40px] text-sm font-bold leading-5 transition group-hover:text-violet-600">
            {product.name}
          </h3>
        </Link>


        <div className="mt-4 flex items-baseline gap-2">

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


        <div className="mt-3 text-xs text-zinc-500">
          {product.merchant.name}
        </div>

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
  currency = "PHP"
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


function stripHtml(
  value: string
) {
  return value
    .replace(
      /<[^>]*>/g,
      " "
    )
    .replace(
      /\s+/g,
      " "
    )
    .trim();
}


function productsShareCategory(
  first: PublicProduct,
  second: PublicProduct
) {
  const categoryIds =
    new Set(
      first.categories.map(
        (category) =>
          category.id
      )
    );


  return second.categories.some(
    (category) =>
      categoryIds.has(
        category.id
      )
  );
}
