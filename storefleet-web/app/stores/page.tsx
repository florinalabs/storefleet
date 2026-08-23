"use client";

import Link from "next/link";
import {
  useMemo,
  useState,
} from "react";

import storesData from "@/data/stores.json";


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


const stores =
  storesData as Store[];


const categories = [
  "All",
  "Groceries",
  "Food",
  "Beverages",
  "Health",
];


export default function StoresPage() {

  const [
    search,
    setSearch,
  ] = useState("");


  const [
    category,
    setCategory,
  ] = useState("All");


  const [
    verifiedOnly,
    setVerifiedOnly,
  ] = useState(false);


  const [
    sort,
    setSort,
  ] = useState("popular");


  const filteredStores =
    useMemo(() => {

      let result =
        [...stores];


      /*
      |--------------------------------------------------------------------------
      | Search
      |--------------------------------------------------------------------------
      */

      if (search.trim()) {

        const query =
          search
            .trim()
            .toLowerCase();


        result =
          result.filter(
            (store) =>
              store.name
                .toLowerCase()
                .includes(query) ||

              store.description
                .toLowerCase()
                .includes(query) ||

              store.city
                .toLowerCase()
                .includes(query) ||

              store.province
                .toLowerCase()
                .includes(query) ||

              store.category
                .toLowerCase()
                .includes(query)
          );

      }


      /*
      |--------------------------------------------------------------------------
      | Category
      |--------------------------------------------------------------------------
      */

      if (category !== "All") {

        result =
          result.filter(
            (store) =>
              store.category ===
              category
          );

      }


      /*
      |--------------------------------------------------------------------------
      | Verification
      |--------------------------------------------------------------------------
      */

      if (verifiedOnly) {

        result =
          result.filter(
            (store) =>
              store.verified
          );

      }


      /*
      |--------------------------------------------------------------------------
      | Sort
      |--------------------------------------------------------------------------
      */

      switch (sort) {

        case "rating":

          result.sort(
            (a, b) =>
              b.rating -
              a.rating
          );

          break;


        case "reviews":

          result.sort(
            (a, b) =>
              b.reviewCount -
              a.reviewCount
          );

          break;


        case "products":

          result.sort(
            (a, b) =>
              b.productCount -
              a.productCount
          );

          break;


        case "name":

          result.sort(
            (a, b) =>
              a.name.localeCompare(
                b.name
              )
          );

          break;


        default:

          result.sort(
            (a, b) =>
              b.orderCount -
              a.orderCount
          );

      }


      return result;

    }, [
      search,
      category,
      verifiedOnly,
      sort,
    ]);


  return (
    <main>

      {/* Hero */}

      <section className="border-b border-zinc-200 bg-zinc-50">

        <div className="mx-auto w-full max-w-7xl px-5 py-14 sm:px-6 lg:px-8 lg:py-20">

          <span className="text-xs font-black uppercase tracking-[0.15em] text-violet-600">
            Local merchants
          </span>


          <div className="mt-5 max-w-3xl">

            <h1 className="text-4xl font-black leading-none tracking-[-0.055em] sm:text-5xl lg:text-6xl">
              Discover stores
              on StoreFleet.
            </h1>


            <p className="mt-6 max-w-2xl text-lg leading-8 text-zinc-600">
              Browse local businesses,
              independent sellers and
              StoreFleet Verified merchants.
            </p>

          </div>

        </div>

      </section>


      {/* Search / Filters */}

      <section className="sticky top-[72px] z-40 border-b border-zinc-200 bg-white/95 backdrop-blur">

        <div className="mx-auto w-full max-w-7xl px-5 py-4 sm:px-6 lg:px-8">

          <div className="flex flex-col gap-4">


            {/* First Row */}

            <div className="flex flex-col gap-3 lg:flex-row">

              {/* Search */}

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
                  placeholder="Search stores, categories or locations"
                  className="min-h-12 w-full rounded-full border border-zinc-300 bg-white pl-12 pr-5 text-sm outline-none transition focus:border-violet-600 focus:ring-4 focus:ring-violet-600/10"
                />

              </div>


              {/* Verified */}

              <button
                type="button"
                onClick={() =>
                  setVerifiedOnly(
                    !verifiedOnly
                  )
                }
                className={`min-h-12 rounded-full border px-5 text-sm font-bold transition ${
                  verifiedOnly
                    ? "border-violet-600 bg-violet-600 text-white"
                    : "border-zinc-300 bg-white text-zinc-700 hover:border-violet-600"
                }`}
              >
                ✓ StoreFleet Verified
              </button>


              {/* Sort */}

              <select
                value={sort}
                onChange={(event) =>
                  setSort(
                    event.target.value
                  )
                }
                className="min-h-12 rounded-full border border-zinc-300 bg-white px-5 text-sm font-semibold outline-none focus:border-violet-600"
              >

                <option value="popular">
                  Most Popular
                </option>

                <option value="rating">
                  Highest Rated
                </option>

                <option value="reviews">
                  Most Reviewed
                </option>

                <option value="products">
                  Most Products
                </option>

                <option value="name">
                  Store Name
                </option>

              </select>

            </div>


            {/* Categories */}

            <div className="flex gap-2 overflow-x-auto pb-1">

              {categories.map(
                (item) => {

                  const active =
                    category ===
                    item;


                  return (
                    <button
                      type="button"
                      key={item}
                      onClick={() =>
                        setCategory(
                          item
                        )
                      }
                      className={`shrink-0 rounded-full px-4 py-2 text-sm font-semibold transition ${
                        active
                          ? "bg-zinc-950 text-white"
                          : "border border-zinc-300 bg-white text-zinc-700 hover:border-zinc-950"
                      }`}
                    >
                      {item}
                    </button>
                  );

                }
              )}

            </div>

          </div>

        </div>

      </section>


      {/* Store Results */}

      <section className="py-10 sm:py-14">

        <div className="mx-auto w-full max-w-7xl px-5 sm:px-6 lg:px-8">


          {/* Results Header */}

          <div className="mb-7 flex items-center justify-between gap-6">

            <div>

              <h2 className="text-xl font-black">
                Stores
              </h2>

              <p className="mt-1 text-sm text-zinc-500">
                {filteredStores.length}{" "}
                {filteredStores.length === 1
                  ? "store"
                  : "stores"}{" "}
                found
              </p>

            </div>


            <Link
              href="/merchant/register"
              className="hidden text-sm font-bold text-violet-600 hover:text-violet-800 sm:inline"
            >
              List your store →
            </Link>

          </div>


          {/* Store Grid */}

          {filteredStores.length >
          0 ? (

            <div className="grid gap-5 md:grid-cols-2 lg:grid-cols-3">

              {filteredStores.map(
                (store) => (

                  <StoreCard
                    key={store.id}
                    store={store}
                  />

                )
              )}

            </div>

          ) : (

            <div className="rounded-3xl border border-dashed border-zinc-300 px-6 py-20 text-center">

              <h2 className="text-2xl font-black">
                No stores found.
              </h2>

              <p className="mt-3 text-zinc-500">
                Try changing your search
                or filters.
              </p>

              <button
                type="button"
                onClick={() => {
                  setSearch("");
                  setCategory("All");
                  setVerifiedOnly(false);
                }}
                className="mt-6 rounded-full bg-zinc-950 px-6 py-3 text-sm font-bold text-white"
              >
                Clear filters
              </button>

            </div>

          )}

        </div>

      </section>


      {/* Merchant CTA */}

      <section className="border-t border-zinc-200 bg-zinc-50">

        <div className="mx-auto flex w-full max-w-7xl flex-col gap-8 px-5 py-16 sm:px-6 lg:flex-row lg:items-center lg:justify-between lg:px-8">

          <div>

            <span className="text-xs font-black uppercase tracking-[0.15em] text-violet-600">
              Sell on StoreFleet
            </span>

            <h2 className="mt-3 text-3xl font-black tracking-[-0.04em] sm:text-4xl">
              Have a local business?
            </h2>

            <p className="mt-3 max-w-xl leading-7 text-zinc-600">
              Create your merchant account
              and bring your store to the
              StoreFleet marketplace.
            </p>

          </div>


          <Link
            href="/merchant/register"
            className="inline-flex min-h-12 shrink-0 items-center justify-center rounded-full bg-violet-600 px-7 text-sm font-bold text-white transition hover:bg-violet-700"
          >
            Become a Merchant
          </Link>

        </div>

      </section>

    </main>
  );
}


/*
|--------------------------------------------------------------------------
| Store Card
|--------------------------------------------------------------------------
*/

function StoreCard({
  store,
}: {
  store: Store;
}) {

  return (
    <article className="group overflow-hidden rounded-3xl border border-zinc-200 bg-white transition duration-200 hover:-translate-y-1 hover:border-zinc-300 hover:shadow-xl hover:shadow-zinc-950/5">


      {/* Store Image */}

      <Link
        href={`/stores/${store.slug}`}
        className="relative block aspect-[16/8] overflow-hidden bg-zinc-100"
      >

        <img
          src={store.image}
          alt={store.name}
          loading="lazy"
          className="h-full w-full object-cover transition duration-300 group-hover:scale-[1.03]"
        />


        <div className="absolute inset-0 bg-gradient-to-t from-black/45 via-transparent to-transparent" />


        {/* Category */}

        <span className="absolute left-4 top-4 rounded-full bg-white/95 px-3 py-1.5 text-xs font-bold shadow-sm backdrop-blur">
          {store.category}
        </span>

      </Link>


      {/* Content */}

      <div className="p-5 sm:p-6">


        {/* Store Heading */}

        <div className="flex items-start justify-between gap-4">

          <div className="min-w-0">

            <Link
              href={`/stores/${store.slug}`}
              className="group/title flex items-center gap-2"
            >

              <h2 className="truncate text-xl font-black tracking-[-0.025em] transition group-hover/title:text-violet-600">
                {store.name}
              </h2>


              {store.verified && (
                <VerifiedBadge />
              )}

            </Link>


            <div className="mt-2 flex items-center gap-1 text-sm text-zinc-500">

              <LocationIcon />

              <span>
                {store.city},{" "}
                {store.province}
              </span>

            </div>

          </div>

        </div>


        {/* Description */}

        <p className="mt-4 line-clamp-2 min-h-[48px] text-sm leading-6 text-zinc-600">
          {store.description}
        </p>


        {/* Rating */}

        <div className="mt-5 flex flex-wrap items-center gap-x-2 gap-y-1 text-sm">

          <div className="flex items-center gap-1">

            <span className="text-amber-400">
              ★
            </span>

            <strong>
              {store.rating.toFixed(
                1
              )}
            </strong>

            <span className="text-zinc-400">
              (
              {formatNumber(
                store.reviewCount
              )}{" "}
              reviews)
            </span>

          </div>

        </div>


        {/* Stats */}

        <div className="mt-5 grid grid-cols-2 gap-3 rounded-2xl bg-zinc-50 p-4">

          <StoreStat
            value={formatNumber(
              store.orderCount
            )}
            label="orders"
          />

          <StoreStat
            value={formatNumber(
              store.productCount
            )}
            label="products"
          />

        </div>


        {/* Footer */}

        <div className="mt-5 flex items-center justify-between gap-4">

          <div>

            {store.verified ? (

              <div className="flex items-center gap-1.5 text-xs font-bold text-violet-700">

                <VerifiedBadge small />

                StoreFleet Verified

              </div>

            ) : (

              <span className="text-xs font-medium text-zinc-400">
                StoreFleet Merchant
              </span>

            )}

          </div>


          <Link
            href={`/stores/${store.slug}`}
            className="text-sm font-bold text-violet-600 transition hover:text-violet-800"
          >
            View Store →
          </Link>

        </div>

      </div>

    </article>
  );
}


/*
|--------------------------------------------------------------------------
| Store Stat
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
    <div>

      <strong className="block text-lg font-black">
        {value}
      </strong>

      <span className="text-xs text-zinc-500">
        {label}
      </span>

    </div>
  );
}


/*
|--------------------------------------------------------------------------
| Verified Badge
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
          : "h-5 w-5 text-[10px]"
      }`}
    >
      ✓
    </span>
  );
}


/*
|--------------------------------------------------------------------------
| Location Icon
|--------------------------------------------------------------------------
*/

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


/*
|--------------------------------------------------------------------------
| Number Format
|--------------------------------------------------------------------------
*/

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