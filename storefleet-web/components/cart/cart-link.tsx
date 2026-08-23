"use client";

import Link from "next/link";

import {
  useCart,
} from "@/components/cart/cart-provider";


export default function CartLink() {
  const {
    itemCount,
    hydrated,
  } = useCart();


  return (
    <Link
      href="/cart"
      className="relative rounded-full bg-zinc-950 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-violet-600"
    >
      Cart

      {hydrated &&
        itemCount > 0 && (
          <span className="absolute -right-2 -top-2 flex h-6 min-w-6 items-center justify-center rounded-full bg-violet-600 px-1.5 text-[11px] font-black text-white ring-2 ring-white">
            {itemCount > 99
              ? "99+"
              : itemCount}
          </span>
        )}
    </Link>
  );
}