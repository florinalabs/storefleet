"use client";

import Link from "next/link";
import {
  usePathname,
  useRouter,
} from "next/navigation";
import {
  useEffect,
  useState,
} from "react";


type Customer = {
  id?: number;
  first_name?: string;
  last_name?: string;
  display_name?: string;
  email?: string;
  phone?: string;
};


type MeResponse = {
  success?: boolean;
  authenticated?: boolean;
  customer?: Customer | null;
};


export default function CustomerAccountActions() {
  const router = useRouter();
  const pathname = usePathname();

  const [customer, setCustomer] =
    useState<Customer | null>(null);

  const [checking, setChecking] =
    useState(true);

  const [loggingOut, setLoggingOut] =
    useState(false);


  /*
  |--------------------------------------------------------------------------
  | Check Customer Session
  |--------------------------------------------------------------------------
  */

  useEffect(() => {
    let cancelled = false;


    async function checkSession() {
      try {
        const response =
          await fetch(
            "/api/account/me",
            {
              method: "GET",
              cache: "no-store",
            }
          );


        if (!response.ok) {
          if (!cancelled) {
            setCustomer(null);
            setChecking(false);
          }

          return;
        }


        const payload =
          (await response.json()) as MeResponse;


        if (!cancelled) {
          if (
            payload.authenticated &&
            payload.customer
          ) {
            setCustomer(
              payload.customer
            );
          } else {
            setCustomer(null);
          }

          setChecking(false);
        }
      } catch (error) {
        console.error(
          "Unable to check customer session:",
          error
        );

        if (!cancelled) {
          setCustomer(null);
          setChecking(false);
        }
      }
    }


    void checkSession();


    return () => {
      cancelled = true;
    };
  }, [pathname]);


  /*
  |--------------------------------------------------------------------------
  | Logout
  |--------------------------------------------------------------------------
  */

  async function handleLogout() {
    if (loggingOut) {
      return;
    }


    setLoggingOut(true);


    try {
      const response =
        await fetch(
          "/api/account/logout",
          {
            method: "POST",
          }
        );


      setCustomer(null);


      if (!response.ok) {
        console.error(
          "Customer logout returned:",
          response.status
        );
      }


      router.push("/");
      router.refresh();
    } catch (error) {
      console.error(
        "Customer logout error:",
        error
      );

      router.refresh();
    } finally {
      setLoggingOut(false);
    }
  }


  /*
  |--------------------------------------------------------------------------
  | Loading State
  |--------------------------------------------------------------------------
  */

  if (checking) {
    return (
      <div
        className="hidden h-5 w-32 lg:block"
        aria-hidden="true"
      />
    );
  }


  /*
  |--------------------------------------------------------------------------
  | Logged Out
  |--------------------------------------------------------------------------
  |
  | StoreFleet | Shop | Stores | For Merchants | Sign In | Cart
  |
  */

  if (!customer) {
    return (
      <>
        <Link
          href="/merchant/login"
          className="transition hover:text-violet-600"
        >
          For Merchants
        </Link>


        <Link
          href="/account/login"
          className="font-semibold transition hover:text-violet-600"
        >
          Sign In
        </Link>
      </>
    );
  }


  /*
  |--------------------------------------------------------------------------
  | Logged In
  |--------------------------------------------------------------------------
  |
  | StoreFleet | Shop | Stores | Account | Logout | Cart
  |
  */

  return (
    <>
      <Link
        href="/account"
        className="font-semibold transition hover:text-violet-600"
      >
        Account
      </Link>


      <button
        type="button"
        onClick={handleLogout}
        disabled={loggingOut}
        className="font-semibold transition hover:text-violet-600 disabled:cursor-not-allowed disabled:opacity-50"
      >
        {loggingOut
          ? "Signing Out..."
          : "Logout"}
      </button>
    </>
  );
}