import type { Metadata } from "next";
import Link from "next/link";
import { notFound } from "next/navigation";

import ProductPurchase from "@/components/cart/product-purchase";
import productsData from "@/data/products.json";


/*
|--------------------------------------------------------------------------
| Types
|--------------------------------------------------------------------------
*/

type ProductStore = {
  id: number;
  name: string;
  slug: string;
  verified: boolean;
};


type Product = {
  id: number;
  slug: string;
  name: string;

  category: string;
  categorySlug: string;

  price: number;
  regularPrice: number;
  currency: string;

  image: string;

  rating: number;
  reviewCount: number;
  orderCount: number;

  stock: number;

  store: ProductStore;
};


const products =
  productsData as Product[];


/*
|--------------------------------------------------------------------------
| Static Routes
|--------------------------------------------------------------------------
*/

export function generateStaticParams() {
  return products.map(
    (product) => ({
      slug: product.slug,
    })
  );
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
  const { slug } =
    await params;


  const product =
    products.find(
      (item) =>
        item.slug === slug
    );


  if (!product) {
    return {
      title: "Product Not Found",
    };
  }


  return {
    title: product.name,

    description:
      `${product.name} from ${product.store.name} on StoreFleet.`,
  };
}


/*
|--------------------------------------------------------------------------
| Product Page
|--------------------------------------------------------------------------
*/

export default async function ProductPage({
  params,
}: {
  params: Promise<{
    slug: string;
  }>;
}) {
  const { slug } =
    await params;


  /*
  |--------------------------------------------------------------------------
  | Find Product
  |--------------------------------------------------------------------------
  */

  const product =
    products.find(
      (item) =>
        item.slug === slug
    );


  if (!product) {
    notFound();
  }


  /*
  |--------------------------------------------------------------------------
  | Related Products
  |--------------------------------------------------------------------------
  */

  const relatedProducts =
    products
      .filter(
        (item) =>
          item.id !== product.id &&
          (
            item.category ===
              product.category ||

            item.store.id ===
              product.store.id
          )
      )
      .slice(0, 4);


  /*
  |--------------------------------------------------------------------------
  | Discount
  |--------------------------------------------------------------------------
  */

  const hasDiscount =
    product.regularPrice >
    product.price;


  const discount =
    hasDiscount
      ? Math.round(
          (
            (
              product.regularPrice -
              product.price
            ) /
            product.regularPrice
          ) *
            100
        )
      : 0;


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
            {product.category}
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

                <img
                  src={product.image}
                  alt={product.name}
                  className="h-full w-full object-cover"
                />


                {/* Discount */}

                {hasDiscount && (

                  <span className="absolute left-5 top-5 rounded-full bg-red-500 px-3 py-1.5 text-sm font-black text-white shadow-sm">
                    -{discount}%
                  </span>

                )}


                {/* Favorite */}

                <button
                  type="button"
                  aria-label={`Save ${product.name}`}
                  className="absolute right-5 top-5 flex h-11 w-11 items-center justify-center rounded-full bg-white/95 text-xl shadow-sm backdrop-blur transition hover:scale-105"
                >
                  ♡
                </button>

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
              {product.category}
            </Link>


            {/* Name */}

            <h1 className="mt-4 text-4xl font-black leading-[1.02] tracking-[-0.055em] sm:text-5xl lg:text-6xl">
              {product.name}
            </h1>


            {/* Rating / Reviews / Orders */}

            <div className="mt-6 flex flex-wrap items-center gap-x-3 gap-y-2 text-sm">

              <div className="flex items-center gap-1">

                <span className="text-lg text-amber-400">
                  ★
                </span>

                <strong>
                  {product.rating.toFixed(1)}
                </strong>

              </div>


              <span className="text-zinc-300">
                |
              </span>


              <a
                href="#reviews"
                className="text-zinc-500 transition hover:text-violet-600"
              >
                {formatNumber(
                  product.reviewCount
                )}{" "}
                reviews
              </a>


              <span className="text-zinc-300">
                |
              </span>


              <span className="text-zinc-500">
                {formatNumber(
                  product.orderCount
                )}{" "}
                sold
              </span>

            </div>


            {/* Price */}

            <div className="mt-8">

              <div className="flex flex-wrap items-baseline gap-3">

                <strong className="text-4xl font-black tracking-[-0.04em] text-violet-700">
                  {formatPrice(
                    product.price
                  )}
                </strong>


                {hasDiscount && (

                  <span className="text-lg text-zinc-400 line-through">
                    {formatPrice(
                      product.regularPrice
                    )}
                  </span>

                )}

              </div>


              {hasDiscount && (

                <div className="mt-2 text-sm font-semibold text-emerald-700">
                  You save{" "}
                  {formatPrice(
                    product.regularPrice -
                      product.price
                  )}
                </div>

              )}

            </div>


            {/* Description */}

            <div className="mt-8 border-t border-zinc-200 pt-8">

              <p className="max-w-2xl leading-8 text-zinc-600">
                {product.name} from{" "}
                <strong className="font-semibold text-zinc-950">
                  {product.store.name}
                </strong>
                . Order online through
                StoreFleet and have your
                purchase prepared by the
                merchant for local delivery.
              </p>

            </div>


            {/* Stock */}

            <div className="mt-6">

              {product.stock > 20 ? (

                <div className="flex items-center gap-2 text-sm">

                  <span className="h-2.5 w-2.5 rounded-full bg-emerald-500" />

                  <span className="font-bold text-emerald-700">
                    In stock
                  </span>

                  <span className="text-zinc-400">
                    ·
                  </span>

                  <span className="text-zinc-500">
                    {product.stock} available
                  </span>

                </div>

              ) : product.stock > 0 ? (

                <div className="flex items-center gap-2 text-sm">

                  <span className="h-2.5 w-2.5 rounded-full bg-orange-500" />

                  <span className="font-bold text-orange-700">
                    Only {product.stock} left
                  </span>

                </div>

              ) : (

                <div className="flex items-center gap-2 text-sm">

                  <span className="h-2.5 w-2.5 rounded-full bg-red-500" />

                  <span className="font-bold text-red-700">
                    Out of stock
                  </span>

                </div>

              )}

            </div>


            {/* Working Purchase Controls */}

            <ProductPurchase
              product={{
                id: product.id,
                slug: product.slug,
                name: product.name,

                price: product.price,
                regularPrice:
                  product.regularPrice,

                image: product.image,
                stock: product.stock,

                store: {
                  id: product.store.id,
                  name:
                    product.store.name,
                  slug:
                    product.store.slug,
                  verified:
                    product.store.verified,
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
                  href={`/stores/${product.store.slug}`}
                  className="flex min-w-0 items-center gap-3"
                >

                  {/* Merchant Avatar */}

                  <div className="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-violet-100 text-sm font-black text-violet-700">
                    {getInitials(
                      product.store.name
                    )}
                  </div>


                  {/* Merchant Name */}

                  <div className="min-w-0">

                    <div className="flex items-center gap-2">

                      <strong className="truncate text-base">
                        {product.store.name}
                      </strong>


                      {product.store
                        .verified && (

                        <VerifiedBadge />

                      )}

                    </div>


                    <span className="mt-1 block text-sm text-zinc-500">

                      {product.store
                        .verified
                        ? "StoreFleet Verified"
                        : "StoreFleet Merchant"}

                    </span>

                  </div>

                </Link>


                <Link
                  href={`/stores/${product.store.slug}`}
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
            text={`Sold by ${product.store.name} through StoreFleet.`}
          />

          <ProductBenefit
            icon="✓"
            title="Secure Payment"
            text="Complete your payment securely through StoreFleet checkout."
          />

          <ProductBenefit
            icon="→"
            title="Local Delivery"
            text="Your merchant prepares the order before local delivery."
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


            <div className="mt-7 space-y-5 leading-8 text-zinc-600">

              <p>
                {product.name} is available
                from {product.store.name} on
                the StoreFleet marketplace.
              </p>

              <p>
                Products displayed on
                StoreFleet are fulfilled by
                their respective local
                merchants.
              </p>

            </div>

          </div>


          {/* Product Details */}

          <div className="rounded-3xl border border-zinc-200 p-6">

            <h3 className="font-black">
              Product details
            </h3>


            <div className="mt-6 space-y-5">

              <InfoRow
                label="Category"
                value={
                  product.category
                }
              />

              <InfoRow
                label="Store"
                value={
                  product.store.name
                }
              />

              <InfoRow
                label="Availability"
                value={
                  product.stock > 0
                    ? `${product.stock} in stock`
                    : "Out of stock"
                }
              />

              <InfoRow
                label="Orders"
                value={`${formatNumber(
                  product.orderCount
                )} sold`}
              />

            </div>

          </div>

        </div>

      </section>


      {/* Reviews */}

      <section
        id="reviews"
        className="scroll-mt-32 border-t border-zinc-200 bg-zinc-50 py-16"
      >

        <div className="mx-auto w-full max-w-7xl px-5 sm:px-6 lg:px-8">


          {/* Heading */}

          <span className="text-xs font-black uppercase tracking-[0.15em] text-violet-600">
            Customer feedback
          </span>


          <h2 className="mt-4 text-3xl font-black tracking-[-0.04em] sm:text-4xl">
            Ratings & reviews
          </h2>


          <div className="mt-9 grid gap-8 lg:grid-cols-[300px_1fr]">


            {/* Rating Summary */}

            <div className="rounded-3xl bg-zinc-950 p-8 text-white">

              <div className="text-6xl font-black">
                {product.rating.toFixed(1)}
              </div>


              <div className="mt-4 flex gap-1 text-xl text-amber-400">
                ★★★★★
              </div>


              <p className="mt-4 text-sm text-zinc-400">
                Based on{" "}
                {formatNumber(
                  product.reviewCount
                )}{" "}
                reviews
              </p>


              <div className="mt-8 space-y-3">

                <RatingBar
                  rating="5"
                  percentage={86}
                />

                <RatingBar
                  rating="4"
                  percentage={10}
                />

                <RatingBar
                  rating="3"
                  percentage={3}
                />

                <RatingBar
                  rating="2"
                  percentage={1}
                />

                <RatingBar
                  rating="1"
                  percentage={0}
                />

              </div>

            </div>


            {/* Hardcoded Reviews */}

            <div className="grid gap-4 md:grid-cols-2">

              <ReviewCard
                name="Maria S."
                rating={5}
                date="2 weeks ago"
                title="Excellent product"
                text={`Very happy with my order from ${product.store.name}. The product arrived in excellent condition and matched the listing.`}
              />

              <ReviewCard
                name="John R."
                rating={5}
                date="3 weeks ago"
                title="Would order again"
                text="Ordering was easy and the product quality was very good. The whole StoreFleet experience was smooth."
              />

              <ReviewCard
                name="Angela P."
                rating={4}
                date="1 month ago"
                title="Good quality"
                text="The item was packed properly and arrived as expected. Good value for the price."
              />

              <ReviewCard
                name="Marco D."
                rating={5}
                date="1 month ago"
                title="Great local seller"
                text={`Fast preparation from ${product.store.name}. I would recommend this merchant.`}
              />

            </div>

          </div>

        </div>

      </section>


      {/* Related Products */}

      {relatedProducts.length > 0 && (

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
                    key={item.id}
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
| Rating Bar
|--------------------------------------------------------------------------
*/

function RatingBar({
  rating,
  percentage,
}: {
  rating: string;
  percentage: number;
}) {
  return (
    <div className="flex items-center gap-3">

      <span className="w-4 text-xs text-zinc-400">
        {rating}
      </span>


      <div className="h-1.5 flex-1 overflow-hidden rounded-full bg-zinc-800">

        <div
          className="h-full rounded-full bg-amber-400"
          style={{
            width: `${percentage}%`,
          }}
        />

      </div>


      <span className="w-8 text-right text-xs text-zinc-500">
        {percentage}%
      </span>

    </div>
  );
}


/*
|--------------------------------------------------------------------------
| Review Card
|--------------------------------------------------------------------------
*/

function ReviewCard({
  name,
  rating,
  date,
  title,
  text,
}: {
  name: string;
  rating: number;
  date: string;
  title: string;
  text: string;
}) {
  return (
    <article className="rounded-3xl border border-zinc-200 bg-white p-6">

      <div className="flex items-start justify-between gap-4">

        <div className="text-sm text-amber-400">
          {"★".repeat(rating)}
        </div>


        <span className="text-xs text-zinc-400">
          {date}
        </span>

      </div>


      <h3 className="mt-4 font-black">
        {title}
      </h3>


      <p className="mt-3 leading-7 text-zinc-600">
        {text}
      </p>


      <div className="mt-5 flex items-center gap-2">

        <div className="flex h-8 w-8 items-center justify-center rounded-full bg-zinc-100 text-xs font-black">
          {getInitials(name)}
        </div>


        <strong className="text-sm">
          {name}
        </strong>


        <span className="text-xs text-zinc-400">
          Verified purchase
        </span>

      </div>

    </article>
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
  product: Product;
}) {
  const hasDiscount =
    product.regularPrice >
    product.price;


  return (
    <article className="group overflow-hidden rounded-2xl border border-zinc-200 bg-white transition hover:-translate-y-1 hover:shadow-lg hover:shadow-zinc-950/5">

      <Link
        href={`/shop/${product.slug}`}
        className="block aspect-square overflow-hidden bg-zinc-100"
      >

        <img
          src={product.image}
          alt={product.name}
          loading="lazy"
          className="h-full w-full object-cover transition duration-300 group-hover:scale-[1.04]"
        />

      </Link>


      <div className="p-4">

        <span className="text-xs font-semibold text-zinc-400">
          {product.category}
        </span>


        <Link
          href={`/shop/${product.slug}`}
        >

          <h3 className="mt-1.5 line-clamp-2 min-h-[40px] text-sm font-bold leading-5 transition group-hover:text-violet-600">
            {product.name}
          </h3>

        </Link>


        <div className="mt-3 flex items-center gap-2 text-xs">

          <span className="text-amber-400">
            ★
          </span>

          <strong>
            {product.rating.toFixed(1)}
          </strong>

          <span className="text-zinc-400">
            {formatNumber(
              product.orderCount
            )}{" "}
            sold
          </span>

        </div>


        <div className="mt-4 flex items-baseline gap-2">

          <strong className="text-lg font-black text-violet-700">
            {formatPrice(
              product.price
            )}
          </strong>


          {hasDiscount && (

            <span className="text-xs text-zinc-400 line-through">
              {formatPrice(
                product.regularPrice
              )}
            </span>

          )}

        </div>

      </div>

    </article>
  );
}


/*
|--------------------------------------------------------------------------
| Verified Badge
|--------------------------------------------------------------------------
*/

function VerifiedBadge() {
  return (
    <span
      title="StoreFleet Verified"
      className="inline-flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-violet-600 text-[10px] font-black text-white"
    >
      ✓
    </span>
  );
}


/*
|--------------------------------------------------------------------------
| Utilities
|--------------------------------------------------------------------------
*/

function formatPrice(
  value: number
) {
  return new Intl.NumberFormat(
    "en-PH",
    {
      style: "currency",
      currency: "PHP",
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