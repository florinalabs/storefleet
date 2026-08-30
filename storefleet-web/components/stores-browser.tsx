"use client";

import Link from "next/link";
import {
  useMemo,
  useState,
} from "react";

import type {
  PublicStoreListItem,
} from "@/lib/storefleet-api";


export default function StoresBrowser({
  stores,
}: {
  stores: PublicStoreListItem[];
}) {
  const [
    search,
    setSearch,
  ] = useState("");

  const [
    status,
    setStatus,
  ] = useState<
    "all" | "open" | "verified"
  >("all");

  const [
    sort,
    setSort,
  ] = useState<
    "name" | "branches" | "open"
  >("name");


  const filteredStores =
    useMemo(() => {
      let result =
        [...stores];

      if (search.trim()) {
        const query =
          search
            .trim()
            .toLowerCase();

        result =
          result.filter(
            (store) => {
              const primary =
                store.primary_branch;

              const haystack = [
                store.name,
                stripHtml(
                  store.description
                ),
                store.address
                  .formatted,
                primary?.name ?? "",
                primary?.address
                  ?.formatted ?? "",
              ]
                .join(" ")
                .toLowerCase();

              return haystack.includes(
                query
              );
            }
          );
      }

      if (status === "open") {
        result =
          result.filter(
            (store) =>
              store.open_branch_count >
              0
          );
      }

      if (status === "verified") {
        result =
          result.filter(
            (store) =>
              store.verified
          );
      }

      switch (sort) {
        case "branches":
          result.sort(
            (a, b) =>
              b.branch_count -
              a.branch_count
          );
          break;

        case "open":
          result.sort(
            (a, b) =>
              b.open_branch_count -
              a.open_branch_count
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
      stores,
      search,
      status,
      sort,
    ]);


  return (
    <main>

      <section className="border-b border-zinc-200 bg-zinc-50">

        <div className="mx-auto w-full max-w-7xl px-5 py-14 sm:px-6 lg:px-8 lg:py-20">

          <span className="text-xs font-black uppercase tracking-[0.15em] text-violet-600">
            Local merchants
          </span>

          <div className="mt-5 max-w-3xl">

            <h1 className="text-4xl font-black leading-none tracking-[-0.055em] sm:text-5xl lg:text-6xl">
              Discover stores on StoreFleet.
            </h1>

            <p className="mt-6 max-w-2xl text-lg leading-8 text-zinc-600">
              Browse live StoreFleet merchants and choose the branch that works for you.
            </p>

          </div>

        </div>

      </section>


      <section className="sticky top-[72px] z-40 border-b border-zinc-200 bg-white/95 backdrop-blur">

        <div className="mx-auto w-full max-w-7xl px-5 py-4 sm:px-6 lg:px-8">

          <div className="flex flex-col gap-3 lg:flex-row">

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
                placeholder="Search stores or locations"
                className="min-h-12 w-full rounded-full border border-zinc-300 bg-white pl-12 pr-5 text-sm outline-none transition focus:border-violet-600 focus:ring-4 focus:ring-violet-600/10"
              />

            </div>


            <div className="flex gap-2 overflow-x-auto">

              <FilterButton
                active={
                  status === "all"
                }
                onClick={() =>
                  setStatus("all")
                }
              >
                All
              </FilterButton>

              <FilterButton
                active={
                  status === "open"
                }
                onClick={() =>
                  setStatus("open")
                }
              >
                Open now
              </FilterButton>

              <FilterButton
                active={
                  status ===
                  "verified"
                }
                onClick={() =>
                  setStatus(
                    "verified"
                  )
                }
              >
                ✓ Verified
              </FilterButton>

            </div>


            <select
              value={sort}
              onChange={(event) =>
                setSort(
                  event.target
                    .value as
                    | "name"
                    | "branches"
                    | "open"
                )
              }
              className="min-h-12 rounded-full border border-zinc-300 bg-white px-5 text-sm font-semibold outline-none focus:border-violet-600"
            >
              <option value="name">
                Store Name
              </option>
              <option value="branches">
                Most Branches
              </option>
              <option value="open">
                Open Branches
              </option>
            </select>

          </div>

        </div>

      </section>


      <section className="py-10 sm:py-14">

        <div className="mx-auto w-full max-w-7xl px-5 sm:px-6 lg:px-8">

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


          {filteredStores.length > 0 ? (

            <div className="grid gap-5 md:grid-cols-2 lg:grid-cols-3">

              {filteredStores.map(
                (store) => (
                  <StoreCard
                    key={
                      store.merchant_id
                    }
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
                Try changing your search or filters.
              </p>

              <button
                type="button"
                onClick={() => {
                  setSearch("");
                  setStatus("all");
                }}
                className="mt-6 rounded-full bg-zinc-950 px-6 py-3 text-sm font-bold text-white"
              >
                Clear filters
              </button>

            </div>

          )}

        </div>

      </section>


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
              Create your merchant account and bring your store to the StoreFleet marketplace.
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


function StoreCard({
  store,
}: {
  store: PublicStoreListItem;
}) {
  const image =
    store.banner_url ||
    store.logo_url;

  const location =
    store.primary_branch
      ?.address?.formatted ||
    store.address.formatted;

  return (
    <article className="group overflow-hidden rounded-3xl border border-zinc-200 bg-white transition duration-200 hover:-translate-y-1 hover:border-zinc-300 hover:shadow-xl hover:shadow-zinc-950/5">

      <Link
        href={`/stores/${store.slug}`}
        className="relative block aspect-[16/8] overflow-hidden bg-zinc-100"
      >

        {image ? (
          <img
            src={image}
            alt={store.name}
            loading="lazy"
            className="h-full w-full object-cover transition duration-300 group-hover:scale-[1.03]"
          />
        ) : (
          <div className="flex h-full items-center justify-center text-5xl font-black text-zinc-300">
            {store.name
              .charAt(0)
              .toUpperCase()}
          </div>
        )}

        <div className="absolute inset-0 bg-gradient-to-t from-black/45 via-transparent to-transparent" />

        <span className="absolute left-4 top-4 rounded-full bg-white/95 px-3 py-1.5 text-xs font-bold shadow-sm backdrop-blur">
          {store.open_branch_count >
          0
            ? `${store.open_branch_count} open`
            : "Closed"}
        </span>

      </Link>


      <div className="p-5 sm:p-6">

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


            {location && (
              <div className="mt-2 flex items-start gap-1 text-sm text-zinc-500">

                <LocationIcon />

                <span className="line-clamp-2">
                  {location}
                </span>

              </div>
            )}

          </div>

        </div>


        <p className="mt-4 line-clamp-2 min-h-[48px] text-sm leading-6 text-zinc-600">
          {stripHtml(
            store.description
          ) ||
            `${store.name} has ${store.branch_count} active ${
              store.branch_count ===
              1
                ? "branch"
                : "branches"
            } on StoreFleet.`}
        </p>


        <div className="mt-5 grid grid-cols-2 gap-3 rounded-2xl bg-zinc-50 p-4">

          <StoreStat
            value={formatNumber(
              store.branch_count
            )}
            label={
              store.branch_count === 1
                ? "branch"
                : "branches"
            }
          />

          <StoreStat
            value={formatNumber(
              store.open_branch_count
            )}
            label="open now"
          />

        </div>


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


function FilterButton({
  active,
  onClick,
  children,
}: {
  active: boolean;
  onClick: () => void;
  children: React.ReactNode;
}) {
  return (
    <button
      type="button"
      onClick={onClick}
      className={`min-h-12 shrink-0 rounded-full border px-5 text-sm font-bold transition ${
        active
          ? "border-zinc-950 bg-zinc-950 text-white"
          : "border-zinc-300 bg-white text-zinc-700 hover:border-zinc-950"
      }`}
    >
      {children}
    </button>
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
