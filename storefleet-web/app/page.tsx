import type { ReactNode } from "react";
import Link from "next/link";

import {
  formatProductPrice,
  getProducts,
} from "@/lib/storefleet";

export default async function HomePage() {
  const products = await getProducts(8);

  const hasProducts =
    products.length > 0;

  return (
    <>
      {/* Hero */}

      <section className="overflow-hidden">
        <div className="mx-auto grid w-full max-w-7xl items-center gap-14 px-5 py-16 sm:px-6 md:py-24 lg:grid-cols-[1.15fr_0.85fr] lg:px-8 lg:py-28">
          <div>
            <Eyebrow>
              Local marketplace
            </Eyebrow>

            <h1 className="max-w-4xl text-5xl font-black leading-[0.95] tracking-[-0.065em] sm:text-6xl lg:text-8xl">
              Everything local.
              <br />
              Delivered faster.
            </h1>

            <p className="mt-8 max-w-2xl text-lg leading-8 text-zinc-600 sm:text-xl">
              Shop products from local stores,
              pay securely online, and have
              everything delivered directly to
              you.
            </p>

            <div className="mt-9 flex flex-wrap gap-3">
              <Link
                href={
                  hasProducts
                    ? "/shop"
                    : "/merchant/register"
                }
                className="inline-flex min-h-12 items-center justify-center rounded-full bg-violet-600 px-6 text-sm font-bold text-white transition hover:bg-violet-700"
              >
                {hasProducts
                  ? "Start shopping"
                  : "Become a Merchant"}
              </Link>

              <Link
                href="/merchant/register"
                className="inline-flex min-h-12 items-center justify-center rounded-full border border-zinc-300 px-6 text-sm font-bold transition hover:border-zinc-950 hover:bg-zinc-950 hover:text-white"
              >
                Merchant Registration
              </Link>
            </div>
          </div>

          {/* Hero Visual */}

          <div className="flex min-h-[430px] flex-col rounded-[32px] bg-gradient-to-br from-violet-600 to-violet-950 p-8 text-white shadow-2xl shadow-violet-900/10 sm:p-10 lg:min-h-[500px]">
            <span className="text-sm font-black tracking-tight">
              STOREFLEET
            </span>

            <div className="my-auto">
              <p className="text-sm text-violet-200">
                One marketplace
              </p>

              <h2 className="mt-3 max-w-md text-4xl font-black leading-[1.02] tracking-[-0.05em] sm:text-5xl">
                Your local stores,
                all in one place.
              </h2>
            </div>

            <div className="grid grid-cols-2 gap-3">
              <Feature
                number="01"
                text="Find stores"
              />

              <Feature
                number="02"
                text="Shop online"
              />

              <Feature
                number="03"
                text="Pay securely"
              />

              <Feature
                number="04"
                text="Get delivered"
              />
            </div>
          </div>
        </div>
      </section>

      {/* Categories */}

      <section className="border-t border-zinc-200 py-20 sm:py-24">
        <div className="mx-auto w-full max-w-7xl px-5 sm:px-6 lg:px-8">
          <SectionHeader
            eyebrow="Browse"
            title="Shop by category"
          />

          <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
            {[
              "Groceries",
              "Beverages",
              "Food",
              "Health",
              "Home",
              "More",
            ].map((category) => (
              <Link
                key={category}
                href="/shop"
                className="group flex min-h-36 items-end rounded-2xl border border-zinc-200 bg-white p-5 transition hover:-translate-y-1 hover:border-violet-300 hover:shadow-lg hover:shadow-zinc-950/5"
              >
                <span className="font-bold transition group-hover:text-violet-600">
                  {category}
                </span>
              </Link>
            ))}
          </div>
        </div>
      </section>

      {/* Products / Empty Marketplace */}

      {hasProducts ? (
        <section className="bg-zinc-50 py-20 sm:py-24">
          <div className="mx-auto w-full max-w-7xl px-5 sm:px-6 lg:px-8">
            <div className="mb-10 flex items-end justify-between gap-6">
              <SectionHeader
                eyebrow="Marketplace"
                title="Popular products"
                noMargin
              />

              <Link
                href="/shop"
                className="shrink-0 text-sm font-bold text-violet-600 hover:text-violet-800"
              >
                View all →
              </Link>
            </div>

            <div className="grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
              {products.map(
                (product) => (
                  <Link
                    key={product.id}
                    href={`/product/${product.slug}`}
                    className="group overflow-hidden rounded-2xl border border-zinc-200 bg-white transition hover:-translate-y-1 hover:shadow-xl hover:shadow-zinc-950/5"
                  >
                    <div className="aspect-square overflow-hidden bg-zinc-100">
                      {product.images?.[0] ? (
                        <img
                          src={
                            product
                              .images[0]
                              .src
                          }
                          alt={
                            product
                              .images[0]
                              .alt ||
                            product.name
                          }
                          className="h-full w-full object-cover transition duration-300 group-hover:scale-[1.03]"
                        />
                      ) : (
                        <div className="flex h-full items-center justify-center text-sm font-bold text-zinc-400">
                          StoreFleet
                        </div>
                      )}
                    </div>

                    <div className="p-5">
                      <span className="text-xs font-medium text-zinc-500">
                        {product
                          .categories?.[0]
                          ?.name ||
                          "StoreFleet"}
                      </span>

                      <h3 className="mt-2 line-clamp-2 font-bold">
                        {product.name}
                      </h3>

                      <div className="mt-5 text-lg font-black">
                        {formatProductPrice(
                          product
                        )}
                      </div>
                    </div>
                  </Link>
                )
              )}
            </div>
          </div>
        </section>
      ) : (
        <section className="bg-zinc-50 py-20 sm:py-28">
          <div className="mx-auto max-w-3xl px-5 text-center sm:px-6">
            <Eyebrow>
              Marketplace
            </Eyebrow>

            <h2 className="text-4xl font-black tracking-[-0.05em] sm:text-6xl">
              Local stores are coming
              to StoreFleet.
            </h2>

            <p className="mx-auto mt-6 max-w-xl text-lg leading-8 text-zinc-600">
              We&apos;re onboarding local
              merchants and preparing the
              StoreFleet marketplace.
            </p>

            <Link
              href="/merchant/register"
              className="mt-8 inline-flex min-h-12 items-center justify-center rounded-full bg-violet-600 px-7 text-sm font-bold text-white transition hover:bg-violet-700"
            >
              Become one of our first merchants
            </Link>
          </div>
        </section>
      )}

      {/* How it works */}

      <section className="py-20 sm:py-24">
        <div className="mx-auto w-full max-w-7xl px-5 sm:px-6 lg:px-8">
          <SectionHeader
            eyebrow="How it works"
            title="From local store to your door."
          />

          <div className="grid border-t border-zinc-200 md:grid-cols-2 lg:grid-cols-4">
            <Step
              number="01"
              title="Find"
              text="Discover products and local stores around you."
            />

            <Step
              number="02"
              title="Shop"
              text="Choose products from StoreFleet merchants."
            />

            <Step
              number="03"
              title="Pay"
              text="Complete your purchase securely online."
            />

            <Step
              number="04"
              title="Delivered"
              text="Track fulfillment and delivery to your door."
            />
          </div>
        </div>
      </section>

      {/* Merchant CTA */}

      <section className="bg-violet-950 text-white">
        <div className="mx-auto flex w-full max-w-7xl flex-col gap-10 px-5 py-20 sm:px-6 lg:flex-row lg:items-end lg:justify-between lg:px-8 lg:py-24">
          <div>
            <span className="text-xs font-black uppercase tracking-[0.15em] text-violet-300">
              For businesses
            </span>

            <h2 className="mt-5 max-w-3xl text-4xl font-black leading-none tracking-[-0.05em] sm:text-6xl">
              Grow your business
              with StoreFleet.
            </h2>

            <p className="mt-6 max-w-2xl text-lg leading-8 text-violet-100/70">
              Sell online, manage branches,
              staff, products, inventory,
              orders, payments and delivery
              through one platform.
            </p>
          </div>

          <Link
            href="/merchant/register"
            className="inline-flex min-h-12 shrink-0 items-center justify-center rounded-full bg-white px-7 text-sm font-bold text-violet-950 transition hover:bg-violet-100"
          >
            Register as a Merchant
          </Link>
        </div>
      </section>
    </>
  );
}


/*
|--------------------------------------------------------------------------
| Components
|--------------------------------------------------------------------------
*/

function Eyebrow({
  children,
}: {
  children: ReactNode;
}) {
  return (
    <span className="mb-5 inline-block text-xs font-black uppercase tracking-[0.15em] text-violet-600">
      {children}
    </span>
  );
}


function Feature({
  number,
  text,
}: {
  number: string;
  text: string;
}) {
  return (
    <div className="rounded-xl border border-white/15 bg-white/5 p-4">
      <span className="text-xs font-bold text-white/50">
        {number}
      </span>

      <div className="mt-2 text-sm font-semibold">
        {text}
      </div>
    </div>
  );
}


function SectionHeader({
  eyebrow,
  title,
  noMargin = false,
}: {
  eyebrow: string;
  title: string;
  noMargin?: boolean;
}) {
  return (
    <div
      className={
        noMargin
          ? ""
          : "mb-10"
      }
    >
      <span className="text-xs font-black uppercase tracking-[0.15em] text-violet-600">
        {eyebrow}
      </span>

      <h2 className="mt-4 text-4xl font-black leading-none tracking-[-0.05em] sm:text-5xl">
        {title}
      </h2>
    </div>
  );
}


function Step({
  number,
  title,
  text,
}: {
  number: string;
  title: string;
  text: string;
}) {
  return (
    <div className="border-b border-zinc-200 py-8 md:px-5 lg:border-b-0 lg:border-r lg:first:pl-0 lg:last:border-r-0">
      <span className="text-xs font-black text-violet-600">
        {number}
      </span>

      <h3 className="mt-10 text-2xl font-black">
        {title}
      </h3>

      <p className="mt-3 max-w-xs leading-7 text-zinc-500">
        {text}
      </p>
    </div>
  );
}