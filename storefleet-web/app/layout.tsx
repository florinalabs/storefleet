import type { Metadata } from "next";
import type { ReactNode } from "react";
import Link from "next/link";

import {
  CartProvider,
} from "@/components/cart/cart-provider";

import CartLink from "@/components/cart/cart-link";

import "./globals.css";


export const metadata: Metadata = {
  title: {
    default: "StoreFleet",
    template: "%s | StoreFleet",
  },

  description:
    "Shop products from local stores and get them delivered.",
};


export default function RootLayout({
  children,
}: {
  children: ReactNode;
}) {
  return (
    <html lang="en">

      <body className="min-h-screen bg-white text-zinc-950 antialiased">

        <CartProvider>

          {/* Header */}

          <header className="sticky top-0 z-50 border-b border-zinc-200/80 bg-white/95 backdrop-blur">

            <div className="mx-auto flex min-h-[72px] w-full max-w-7xl items-center gap-8 px-5 sm:px-6 lg:px-8">

              {/* Logo */}

              <Link
                href="/"
                className="text-2xl font-black tracking-[-0.05em]"
              >
                StoreFleet
              </Link>


              {/* Marketplace Navigation */}

              <nav className="ml-auto hidden items-center gap-8 text-sm font-medium lg:flex">

                <Link
                  href="/shop"
                  className="transition hover:text-violet-600"
                >
                  Shop
                </Link>

                <Link
                  href="/stores"
                  className="transition hover:text-violet-600"
                >
                  Stores
                </Link>

                <Link
                  href="/merchant/register"
                  className="transition hover:text-violet-600"
                >
                  Become a Merchant
                </Link>

              </nav>


              {/* Account Actions */}

              <div className="ml-auto flex items-center gap-3 lg:ml-4">

                {/* Customer Registration */}

                <Link
                  href="/account/register"
                  className="hidden text-sm font-semibold transition hover:text-violet-600 sm:inline"
                >
                  Create Account
                </Link>


                {/* Merchant Login */}

                <Link
                  href="/merchant/login"
                  className="hidden text-sm font-semibold transition hover:text-violet-600 md:inline"
                >
                  Merchant Login
                </Link>


                {/* Live Cart */}

                <CartLink />

              </div>

            </div>

          </header>


          {/* Page */}

          <main>
            {children}
          </main>


          {/* Footer */}

          <footer className="border-t border-zinc-200">

            <div className="mx-auto grid w-full max-w-7xl gap-12 px-5 py-16 sm:px-6 md:grid-cols-2 lg:grid-cols-4 lg:px-8">

              {/* Brand */}

              <div>

                <Link
                  href="/"
                  className="text-2xl font-black tracking-[-0.05em]"
                >
                  StoreFleet
                </Link>


                <p className="mt-4 max-w-xs text-sm leading-6 text-zinc-500">
                  Everything local.
                  Delivered faster.
                </p>

              </div>


              {/* Marketplace */}

              <FooterColumn
                title="Marketplace"
                links={[
                  {
                    label: "Shop",
                    href: "/shop",
                  },
                  {
                    label: "Stores",
                    href: "/stores",
                  },
                  {
                    label: "Cart",
                    href: "/cart",
                  },
                  {
                    label: "Create Account",
                    href: "/account/register",
                  },
                ]}
              />


              {/* Merchants */}

              <FooterColumn
                title="Merchants"
                links={[
                  {
                    label: "Become a Merchant",
                    href: "/merchant/register",
                  },
                  {
                    label: "Merchant Login",
                    href: "/merchant/login",
                  },
                ]}
              />


              {/* StoreFleet */}

              <FooterColumn
                title="StoreFleet"
                links={[
                  {
                    label: "About",
                    href: "/about",
                  },
                  {
                    label: "Contact",
                    href: "/contact",
                  },
                ]}
              />

            </div>


            {/* Footer Bottom */}

            <div className="border-t border-zinc-200">

              <div className="mx-auto flex max-w-7xl flex-col gap-2 px-5 py-6 text-sm text-zinc-500 sm:px-6 md:flex-row md:items-center md:justify-between lg:px-8">

                <span>
                  © {new Date().getFullYear()} StoreFleet
                </span>


                <span>
                  Local commerce, connected.
                </span>

              </div>

            </div>

          </footer>

        </CartProvider>

      </body>

    </html>
  );
}


/*
|--------------------------------------------------------------------------
| Footer Column
|--------------------------------------------------------------------------
*/

function FooterColumn({
  title,
  links,
}: {
  title: string;

  links: {
    label: string;
    href: string;
  }[];
}) {
  return (
    <div>

      <h3 className="text-sm font-bold">
        {title}
      </h3>


      <div className="mt-4 flex flex-col gap-3">

        {links.map((link) => (

          <Link
            key={link.href}
            href={link.href}
            className="text-sm text-zinc-500 transition hover:text-violet-600"
          >
            {link.label}
          </Link>

        ))}

      </div>

    </div>
  );
}