"use client";

import Link from "next/link";
import {
  useMemo,
  useState,
} from "react";

import productsData from "@/data/products.json";


type Store = {
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

  store: Store;
};


const products =
  productsData as Product[];


const categories = [
  "All",
  "Groceries",
  "Beverages",
  "Food",
  "Health",
];


export default function ShopPage() {
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
  ] = useState("popular");


  const filteredProducts =
    useMemo(() => {
      let result =
        [...products];


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
            (product) =>
              product.category ===
              selectedCategory
          );
      }


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
            (product) =>
              product.name
                .toLowerCase()
                .includes(query) ||
              product.store.name
                .toLowerCase()
                .includes(query) ||
              product.category
                .toLowerCase()
                .includes(query)
          );
      }


      /*
      |--------------------------------------------------------------------------
      | Sorting
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

        case "orders":
          result.sort(
            (a, b) =>
              b.orderCount -
              a.orderCount
          );
          break;

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
                Discover products from local
                StoreFleet merchants.
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
                placeholder="Search products or stores"
                className="min-h-12 w-full rounded-full border border-zinc-300 bg-white pl-12 pr-5 text-sm outline-none transition focus:border-violet-600 focus:ring-4 focus:ring-violet-600/10"
              />

            </div>


            {/* Categories */}

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


            {/* Sort */}

            <select
              value={sort}
              onChange={(event) =>
                setSort(
                  event.target.value
                )
              }
              className="min-h-12 rounded-full border border-zinc-300 bg-white px-4 text-sm font-semibold outline-none focus:border-violet-600"
            >
              <option value="popular">
                Popular
              </option>

              <option value="rating">
                Highest Rated
              </option>

              <option value="orders">
                Most Orders
              </option>

              <option value="price-low">
                Price: Low to High
              </option>

              <option value="price-high">
                Price: High to Low
              </option>
            </select>

          </div>

        </div>

      </section>


      {/* Products */}

      <section className="py-10 sm:py-14">

        <div className="mx-auto w-full max-w-7xl px-5 sm:px-6 lg:px-8">

          {filteredProducts.length >
          0 ? (

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
                Try another search or
                category.
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
  product: Product;
}) {
  const hasDiscount =
    product.regularPrice >
    product.price;


  const discount =
    hasDiscount
      ? Math.round(
          ((product.regularPrice -
            product.price) /
            product.regularPrice) *
            100
        )
      : 0;


  return (
    <article className="group relative overflow-hidden rounded-2xl border border-zinc-200 bg-white transition duration-200 hover:-translate-y-1 hover:border-zinc-300 hover:shadow-xl hover:shadow-zinc-950/5">

      {/* Image */}

      <Link
        href={`/shop/${product.slug}`}
        className="relative block aspect-square overflow-hidden bg-zinc-100"
      >

        <img
          src={product.image}
          alt={product.name}
          loading="lazy"
          className="h-full w-full object-cover transition duration-300 group-hover:scale-[1.04]"
        />


        {/* Discount */}

        {hasDiscount && (

          <span className="absolute left-3 top-3 rounded-full bg-red-500 px-2.5 py-1 text-xs font-black text-white">
            -{discount}%
          </span>

        )}


        {/* Favorite */}

        <button
          type="button"
          onClick={(event) => {
            event.preventDefault();
          }}
          aria-label={`Save ${product.name}`}
          className="absolute right-3 top-3 flex h-9 w-9 items-center justify-center rounded-full bg-white/90 text-lg shadow-sm backdrop-blur transition hover:bg-white"
        >
          ♡
        </button>

      </Link>


      {/* Content */}

      <div className="p-4 sm:p-5">

        {/* Category */}

        <span className="text-xs font-semibold text-zinc-400">
          {product.category}
        </span>


        {/* Product Name */}

        <Link
          href={`/shop/${product.slug}`}
        >

          <h2 className="mt-1.5 line-clamp-2 min-h-[44px] text-sm font-bold leading-5 transition group-hover:text-violet-600 sm:text-base">
            {product.name}
          </h2>

        </Link>


        {/* Rating / Orders */}

        <div className="mt-3 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs">

          <div className="flex items-center gap-1">

            <span className="text-amber-400">
              ★
            </span>

            <strong>
              {product.rating.toFixed(
                1
              )}
            </strong>

            <span className="text-zinc-400">
              (
              {formatNumber(
                product.reviewCount
              )}
              )
            </span>

          </div>


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

          <strong className="text-lg font-black tracking-tight text-violet-700 sm:text-xl">
            {formatPrice(
              product.price
            )}
          </strong>

          {hasDiscount && (

            <span className="text-xs text-zinc-400 line-through sm:text-sm">
              {formatPrice(
                product.regularPrice
              )}
            </span>

          )}

        </div>


        {/* Store */}

        <div className="mt-4 border-t border-zinc-100 pt-4">

          <Link
            href={`/stores/${product.store.slug}`}
            className="flex min-w-0 items-center gap-2"
          >

            <div className="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-violet-100 text-[10px] font-black text-violet-700">
              {getInitials(
                product.store.name
              )}
            </div>


            <div className="min-w-0">

              <div className="flex items-center gap-1">

                <span className="truncate text-xs font-semibold text-zinc-600">
                  {product.store.name}
                </span>

                {product.store
                  .verified && (

                  <VerifiedBadge />

                )}

              </div>

            </div>

          </Link>

        </div>


        {/* Stock */}

        {product.stock <= 20 && (

          <div className="mt-3 text-xs font-semibold text-orange-600">
            Only {product.stock} left
          </div>

        )}

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
      className="inline-flex h-4 w-4 shrink-0 items-center justify-center rounded-full bg-violet-600 text-[9px] font-black text-white"
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