"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";
import {
  FormEvent,
  useState,
} from "react";


type LoginResponse = {
  success?: boolean;
  message?: string;
  code?: string;

  customer?: {
    id?: number;
    first_name?: string;
    last_name?: string;
    display_name?: string;
    email?: string;
    phone?: string;
  } | null;
};


/*
|--------------------------------------------------------------------------
| Safe Redirect
|--------------------------------------------------------------------------
|
| Only allow internal StoreFleet paths.
|
| Allowed:
| /checkout
| /account
| /shop
|
| Rejected:
| https://example.com
| //example.com
|
*/

function getSafeNextPath(
  value: string | null
): string {
  if (
    !value ||
    !value.startsWith("/") ||
    value.startsWith("//")
  ) {
    return "/shop";
  }

  return value;
}


export default function CustomerLoginPage() {
  const router = useRouter();


  const [email, setEmail] =
    useState("");

  const [password, setPassword] =
    useState("");

  const [remember, setRemember] =
    useState(false);

  const [loading, setLoading] =
    useState(false);

  const [error, setError] =
    useState<string | null>(null);


  /*
  |--------------------------------------------------------------------------
  | Login
  |--------------------------------------------------------------------------
  */

  async function handleSubmit(
    event: FormEvent<HTMLFormElement>
  ) {
    event.preventDefault();

    setError(null);


    /*
    |--------------------------------------------------------------------------
    | Client Validation
    |--------------------------------------------------------------------------
    */

    if (
      !email.trim() ||
      !password
    ) {
      setError(
        "Please enter your email and password."
      );

      return;
    }


    /*
    |--------------------------------------------------------------------------
    | Submit Login
    |--------------------------------------------------------------------------
    */

    setLoading(true);

    try {
      const response =
        await fetch(
          "/api/account/login",
          {
            method: "POST",

            headers: {
              "Content-Type":
                "application/json",
            },

            body: JSON.stringify({
              email:
                email.trim(),

              password,

              remember,
            }),
          }
        );


      const payload =
        (await response.json()) as LoginResponse;


      /*
      |--------------------------------------------------------------------------
      | Login Error
      |--------------------------------------------------------------------------
      */

      if (!response.ok) {
        setError(
          payload.message ||
            "Invalid email or password."
        );

        return;
      }


      /*
      |--------------------------------------------------------------------------
      | Login Successful
      |--------------------------------------------------------------------------
      |
      | /api/account/login stores the customer session in an HttpOnly
      | cookie.
      |
      | If the customer was redirected here from a protected page such
      | as /checkout, send them back there after authentication.
      |
      | Otherwise use /shop as the normal login destination.
      |
      */

      const searchParams =
        new URLSearchParams(
          window.location.search
        );


      const nextPath =
        getSafeNextPath(
          searchParams.get("next")
        );


      router.push(nextPath);

      router.refresh();
    } catch (error) {
      console.error(
        "Customer login error:",
        error
      );

      setError(
        "Something went wrong. Please try again."
      );
    } finally {
      setLoading(false);
    }
  }


  return (
    <section className="min-h-screen bg-neutral-50">

      <div className="mx-auto flex min-h-screen max-w-7xl items-center justify-center px-4 py-12 sm:px-6 lg:px-8">

        <div className="w-full max-w-md">

          <div className="rounded-3xl border border-neutral-200 bg-white p-6 shadow-sm sm:p-8">

            {/* Header */}

            <div className="mb-8">

              <Link
                href="/"
                className="inline-flex text-sm font-medium text-neutral-600 transition hover:text-neutral-950"
              >
                ← Back to StoreFleet
              </Link>


              <h1 className="mt-6 text-3xl font-bold tracking-tight text-neutral-950">
                Sign in
              </h1>


              <p className="mt-2 text-sm leading-6 text-neutral-600">
                Sign in to your StoreFleet
                customer account.
              </p>

            </div>


            {/* Error */}

            {error && (

              <div
                className="mb-6 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"
                role="alert"
              >
                {error}
              </div>

            )}


            {/* Login Form */}

            <form
              onSubmit={handleSubmit}
              className="space-y-5"
            >

              {/* Email */}

              <div>

                <label
                  htmlFor="email"
                  className="mb-2 block text-sm font-medium text-neutral-800"
                >
                  Email address
                </label>


                <input
                  id="email"
                  name="email"
                  type="email"
                  autoComplete="email"
                  inputMode="email"
                  value={email}
                  onChange={(event) =>
                    setEmail(
                      event.target.value
                    )
                  }
                  disabled={loading}
                  required
                  autoFocus
                  className="w-full rounded-xl border border-neutral-300 bg-white px-4 py-3 text-sm text-neutral-950 outline-none transition placeholder:text-neutral-400 focus:border-neutral-950 focus:ring-2 focus:ring-neutral-950/10 disabled:cursor-not-allowed disabled:bg-neutral-100"
                  placeholder="you@example.com"
                />

              </div>


              {/* Password */}

              <div>

                <label
                  htmlFor="password"
                  className="mb-2 block text-sm font-medium text-neutral-800"
                >
                  Password
                </label>


                <input
                  id="password"
                  name="password"
                  type="password"
                  autoComplete="current-password"
                  value={password}
                  onChange={(event) =>
                    setPassword(
                      event.target.value
                    )
                  }
                  disabled={loading}
                  required
                  className="w-full rounded-xl border border-neutral-300 bg-white px-4 py-3 text-sm text-neutral-950 outline-none transition placeholder:text-neutral-400 focus:border-neutral-950 focus:ring-2 focus:ring-neutral-950/10 disabled:cursor-not-allowed disabled:bg-neutral-100"
                  placeholder="Enter your password"
                />

              </div>


              {/* Remember Me */}

              <div className="flex items-center gap-3">

                <input
                  id="remember"
                  name="remember"
                  type="checkbox"
                  checked={remember}
                  onChange={(event) =>
                    setRemember(
                      event.target.checked
                    )
                  }
                  disabled={loading}
                  className="h-4 w-4 rounded border-neutral-300"
                />


                <label
                  htmlFor="remember"
                  className="text-sm text-neutral-600"
                >
                  Keep me signed in
                </label>

              </div>


              {/* Submit */}

              <button
                type="submit"
                disabled={loading}
                className="flex w-full items-center justify-center rounded-xl bg-neutral-950 px-5 py-3.5 text-sm font-semibold text-white transition hover:bg-neutral-800 disabled:cursor-not-allowed disabled:opacity-60"
              >
                {loading
                  ? "Signing in..."
                  : "Sign in"}
              </button>

            </form>


            {/* Registration */}

            <div className="mt-8 border-t border-neutral-200 pt-6 text-center">

              <p className="text-sm text-neutral-600">

                Don&apos;t have an account?{" "}

                <Link
                  href="/account/register"
                  className="font-semibold text-neutral-950 underline underline-offset-4"
                >
                  Create account
                </Link>

              </p>

            </div>


            {/* Merchant */}

            <div className="mt-5 text-center">

              <p className="text-xs text-neutral-500">

                Are you a merchant?{" "}

                <Link
                  href="/merchant/login"
                  className="font-medium text-neutral-700 hover:text-neutral-950"
                >
                  Merchant Login
                </Link>

              </p>

            </div>

          </div>

        </div>

      </div>

    </section>
  );
}