import type { Metadata } from "next";
import type { ReactNode } from "react";
import Link from "next/link";

import {
  CartProvider,
} from "@/components/cart/cart-provider";

import CustomerAccountActions from "@/components/account/customer-account-actions";
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

            <div className="mx-auto flex min-h-[72px] w-full max-w-7xl items-center px-5 sm:px-6 lg:px-8">

              {/* Logo */}

              <Link
                href="/"
                className="shrink-0 text-2xl font-black tracking-[-0.05em]"
              >
                StoreFleet
              </Link>


              {/* Desktop Navigation */}

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


                {/*
                |--------------------------------------------------------------------------
                | Dynamic Account Navigation
                |--------------------------------------------------------------------------
                |
                | Logged out:
                | For Merchants | Sign In
                |
                | Logged in:
                | Account | Logout
                |
                */}

                <CustomerAccountActions />


                {/* Cart */}

                <CartLink />

              </nav>


              {/* Small Screen Cart */}

              <div className="ml-auto flex items-center lg:hidden">

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
                ]}
              />


              {/* Customers */}

              <FooterColumn
                title="Customers"
                links={[
                  {
                    label: "Create Account",
                    href: "/account/register",
                  },
                  {
                    label: "Sign In",
                    href: "/account/login",
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