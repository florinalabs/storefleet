import type { Metadata } from "next";
import Link from "next/link";
import { notFound } from "next/navigation";

import storesData from "@/data/stores.json";
import productsData from "@/data/products.json";


type Store = {
  id: number;
  slug: string;
  name: string;

  description: string;

  category: string;
  categorySlug: string;

  city: string;
  province: string;

  verified: boolean;

  rating: number;
  reviewCount: number;
  orderCount: number;
  productCount: number;

  image: string;
};


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


const stores =
  storesData as Store[];

const products =
  productsData as Product[];


/*
|--------------------------------------------------------------------------
| Static Params
|--------------------------------------------------------------------------
*/

export function generateStaticParams() {
  return stores.map((store) => ({
    slug: store.slug,
  }));
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

  const store =
    stores.find(
      (item) =>
        item.slug === slug
    );

  if (!store) {
    return {
      title: "Store Not Found",
    };
  }

  return {
    title: store.name,
    description:
      store.description,
  };
}


/*
|--------------------------------------------------------------------------
| Store Page
|--------------------------------------------------------------------------
*/

export default async function StorePage({
  params,
}: {
  params: Promise<{
    slug: string;
  }>;
}) {
  const { slug } =
    await params;


  const store =
    stores.find(
      (item) =>
        item.slug === slug
    );


  if (!store) {
    notFound();
  }


  const storeProducts =
    products.filter(
      (product) =>
        product.store.slug ===
        store.slug
    );


  return (
    <main>

      {/* Store Hero */}

      <section className="bg-zinc-950 text-white">

        <div className="mx-auto w-full max-w-7xl px-5 py-8 sm:px-6 lg:px-8">

          {/* Breadcrumb */}

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


          <div className="grid gap-8 lg:grid-cols-[360px_1fr] lg:items-center">

            {/* Image */}

            <div className="aspect-[16/10] overflow-hidden rounded-3xl bg-zinc-800">

              <img
                src={store.image}
                alt={store.name}
                className="h-full w-full object-cover"
              />

            </div>


            {/* Store Details */}

            <div>

              <span className="inline-flex rounded-full bg-white/10 px-3 py-1.5 text-xs font-bold text-zinc-200">
                {store.category}
              </span>


              <div className="mt-5 flex flex-wrap items-center gap-3">

                <h1 className="text-4xl font-black leading-none tracking-[-0.055em] sm:text-5xl lg:text-6xl">
                  {store.name}
                </h1>

                {store.verified && (
                  <VerifiedBadge />
                )}

              </div>


              {store.verified && (

                <div className="mt-4 inline-flex items-center gap-2 rounded-full bg-violet-500/15 px-3 py-1.5 text-sm font-bold text-violet-200">

                  <VerifiedBadge small />

                  StoreFleet Verified

                </div>

              )}


              <p className="mt-6 max-w-3xl text-base leading-7 text-zinc-300 sm:text-lg">
                {store.description}
              </p>


              {/* Location */}

              <div className="mt-6 flex items-center gap-2 text-sm text-zinc-300">

                <LocationIcon />

                {store.city},{" "}
                {store.province}

              </div>


              {/* Stats */}

              <div className="mt-8 grid max-w-2xl grid-cols-2 gap-px overflow-hidden rounded-2xl bg-white/10 sm:grid-cols-4">

                <StoreStat
                  value={
                    store.rating.toFixed(
                      1
                    ) + " ★"
                  }
                  label="Rating"
                />

                <StoreStat
                  value={formatNumber(
                    store.reviewCount
                  )}
                  label="Reviews"
                />

                <StoreStat
                  value={formatNumber(
                    store.orderCount
                  )}
                  label="Orders"
                />

                <StoreStat
                  value={formatNumber(
                    store.productCount
                  )}
                  label="Products"
                />

              </div>

            </div>

          </div>

        </div>

      </section>


      {/* Store Navigation */}

      <section className="border-b border-zinc-200 bg-white">

        <div className="mx-auto flex w-full max-w-7xl gap-8 overflow-x-auto px-5 sm:px-6 lg:px-8">

          <a
            href="#products"
            className="border-b-2 border-violet-600 py-5 text-sm font-bold text-violet-600"
          >
            Products
          </a>

          <a
            href="#about"
            className="py-5 text-sm font-semibold text-zinc-500 transition hover:text-zinc-950"
          >
            About
          </a>

          <a
            href="#reviews"
            className="py-5 text-sm font-semibold text-zinc-500 transition hover:text-zinc-950"
          >
            Reviews
          </a>

        </div>

      </section>


      {/* Products */}

      <section
        id="products"
        className="scroll-mt-32 py-12 sm:py-16"
      >

        <div className="mx-auto w-full max-w-7xl px-5 sm:px-6 lg:px-8">

          <div className="mb-8 flex items-end justify-between gap-6">

            <div>

              <span className="text-xs font-black uppercase tracking-[0.15em] text-violet-600">
                Store products
              </span>

              <h2 className="mt-3 text-3xl font-black tracking-[-0.04em] sm:text-4xl">
                Shop {store.name}
              </h2>

            </div>


            <span className="text-sm text-zinc-500">
              {storeProducts.length}{" "}
              {storeProducts.length === 1
                ? "product"
                : "products"}
            </span>

          </div>


          {storeProducts.length > 0 ? (

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
                No products yet.
              </h3>

              <p className="mt-2 text-zinc-500">
                This store has not listed
                products yet.
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

            <p className="mt-5 max-w-3xl leading-8 text-zinc-600">
              {store.description}
            </p>

          </div>


          <div className="rounded-3xl border border-zinc-200 bg-white p-6">

            <h3 className="font-black">
              Store information
            </h3>

            <div className="mt-6 space-y-5">

              <InfoRow
                label="Category"
                value={store.category}
              />

              <InfoRow
                label="Location"
                value={`${store.city}, ${store.province}`}
              />

              <InfoRow
                label="Status"
                value={
                  store.verified
                    ? "StoreFleet Verified"
                    : "StoreFleet Merchant"
                }
              />

              <InfoRow
                label="Products"
                value={`${store.productCount} products`}
              />

            </div>

          </div>

        </div>

      </section>


      {/* Reviews */}

      <section
        id="reviews"
        className="scroll-mt-32 border-t border-zinc-200 py-16"
      >

        <div className="mx-auto w-full max-w-7xl px-5 sm:px-6 lg:px-8">

          <span className="text-xs font-black uppercase tracking-[0.15em] text-violet-600">
            Customer feedback
          </span>

          <h2 className="mt-4 text-3xl font-black tracking-[-0.04em]">
            Store reviews
          </h2>


          <div className="mt-8 grid gap-6 lg:grid-cols-[280px_1fr]">

            <div className="rounded-3xl bg-zinc-950 p-8 text-white">

              <div className="text-5xl font-black">
                {store.rating.toFixed(
                  1
                )}
              </div>

              <div className="mt-3 text-xl tracking-widest text-amber-400">
                ★★★★★
              </div>

              <div className="mt-3 text-sm text-zinc-400">
                Based on{" "}
                {formatNumber(
                  store.reviewCount
                )}{" "}
                reviews
              </div>

            </div>


            <div className="grid gap-4 md:grid-cols-2">

              <ReviewCard
                name="Maria S."
                rating={5}
                text={`Great experience ordering from ${store.name}. Products arrived in excellent condition.`}
              />

              <ReviewCard
                name="John R."
                rating={5}
                text="Fast service, good product quality and a smooth StoreFleet ordering experience."
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
  product: Product;
}) {
  const hasDiscount =
    product.regularPrice >
    product.price;

  return (
    <article className="group overflow-hidden rounded-2xl border border-zinc-200 bg-white transition hover:-translate-y-1 hover:shadow-xl hover:shadow-zinc-950/5">

      {/* Product Image */}

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


      {/* Product Content */}

      <div className="p-4">

        {/* Category */}

        <span className="text-xs font-semibold text-zinc-400">
          {product.category}
        </span>


        {/* Product Name */}

        <Link
          href={`/shop/${product.slug}`}
        >
          <h3 className="mt-1.5 line-clamp-2 min-h-[40px] text-sm font-bold leading-5 transition group-hover:text-violet-600 sm:text-base">
            {product.name}
          </h3>
        </Link>


        {/* Rating / Reviews / Orders */}

        <div className="mt-3 flex flex-wrap items-center gap-2 text-xs">

          <span className="text-amber-400">
            ★
          </span>

          <strong>
            {product.rating.toFixed(1)}
          </strong>

          <span className="text-zinc-400">
            (
            {formatNumber(
              product.reviewCount
            )}
            )
          </span>

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

        <div className="mt-4 flex flex-wrap items-baseline gap-2">

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


        {/* Stock */}

        {product.stock <= 20 &&
          product.stock > 0 && (
            <div className="mt-3 text-xs font-semibold text-orange-600">
              Only {product.stock} left
            </div>
          )}

        {product.stock === 0 && (
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

function VerifiedBadge({
  small = false,
}: {
  small?: boolean;
}) {
  return (
    <span
      title="StoreFleet Verified"
      className={`inline-flex shrink-0 items-center justify-center rounded-full bg-violet-600 font-black text-white ${
        small
          ? "h-4 w-4 text-[9px]"
          : "h-6 w-6 text-xs"
      }`}
    >
      ✓
    </span>
  );
}


function StoreStat({
  value,
  label,
}: {
  value: string;
  label: string;
}) {
  return (
    <div className="bg-white/5 p-5">

      <strong className="block text-xl font-black">
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

      <strong className="text-right text-sm">
        {value}
      </strong>

    </div>
  );
}


function ReviewCard({
  name,
  rating,
  text,
}: {
  name: string;
  rating: number;
  text: string;
}) {
  return (
    <div className="rounded-3xl border border-zinc-200 p-6">

      <div className="text-amber-400">
        {"★".repeat(rating)}
      </div>

      <p className="mt-4 leading-7 text-zinc-600">
        {text}
      </p>

      <strong className="mt-5 block text-sm">
        {name}
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
      className="h-4 w-4 shrink-0"
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