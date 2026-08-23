"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";
import { FormEvent, useState } from "react";


type RegistrationResponse = {
  success?: boolean;
  message?: string;
  customer?: {
    id?: number;
    first_name?: string;
    last_name?: string;
    display_name?: string;
    email?: string;
    phone?: string;
  } | null;
  code?: string;
};


export default function CustomerRegisterPage() {
  const router = useRouter();

  const [firstName, setFirstName] =
    useState("");

  const [lastName, setLastName] =
    useState("");

  const [email, setEmail] =
    useState("");

  const [phone, setPhone] =
    useState("");

  const [password, setPassword] =
    useState("");

  const [confirmPassword, setConfirmPassword] =
    useState("");

  const [loading, setLoading] =
    useState(false);

  const [error, setError] =
    useState<string | null>(null);


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
      !firstName.trim() ||
      !lastName.trim() ||
      !email.trim() ||
      !phone.trim() ||
      !password
    ) {
      setError(
        "Please complete all required fields."
      );

      return;
    }


    if (password.length < 10) {
      setError(
        "Password must be at least 10 characters."
      );

      return;
    }


    if (password !== confirmPassword) {
      setError(
        "Passwords do not match."
      );

      return;
    }


    /*
    |--------------------------------------------------------------------------
    | Registration
    |--------------------------------------------------------------------------
    */

    setLoading(true);

    try {
      const response =
        await fetch(
          "/api/account/register",
          {
            method: "POST",
            headers: {
              "Content-Type":
                "application/json",
            },
            body: JSON.stringify({
              first_name:
                firstName.trim(),

              last_name:
                lastName.trim(),

              email:
                email.trim(),

              phone:
                phone.trim(),

              password,
            }),
          }
        );


      const payload =
        (await response.json()) as RegistrationResponse;


      if (!response.ok) {
        setError(
          payload.message ||
            "Unable to create your account."
        );

        return;
      }


      /*
      |--------------------------------------------------------------------------
      | Customer Is Already Logged In
      |--------------------------------------------------------------------------
      |
      | /api/account/register creates the secure HttpOnly session cookie.
      |
      | The account dashboard will be added in a later issue, so for now
      | customers are sent back to the marketplace after registration.
      |
      */

      router.push("/shop");
      router.refresh();
    } catch (error) {
      console.error(
        "Customer registration error:",
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
    <main className="min-h-screen bg-neutral-50">
      <div className="mx-auto flex min-h-screen max-w-7xl items-center justify-center px-4 py-12 sm:px-6 lg:px-8">
        <div className="w-full max-w-lg">
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
                Create your account
              </h1>

              <p className="mt-2 text-sm leading-6 text-neutral-600">
                Register to shop from local
                StoreFleet merchants and manage
                your orders.
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


            {/* Form */}

            <form
              onSubmit={handleSubmit}
              className="space-y-5"
            >
              <div className="grid gap-5 sm:grid-cols-2">
                <div>
                  <label
                    htmlFor="firstName"
                    className="mb-2 block text-sm font-medium text-neutral-800"
                  >
                    First name
                  </label>

                  <input
                    id="firstName"
                    name="firstName"
                    type="text"
                    autoComplete="given-name"
                    value={firstName}
                    onChange={(event) =>
                      setFirstName(
                        event.target.value
                      )
                    }
                    disabled={loading}
                    required
                    className="w-full rounded-xl border border-neutral-300 bg-white px-4 py-3 text-sm text-neutral-950 outline-none transition placeholder:text-neutral-400 focus:border-neutral-950 focus:ring-2 focus:ring-neutral-950/10 disabled:cursor-not-allowed disabled:bg-neutral-100"
                    placeholder="Juan"
                  />
                </div>


                <div>
                  <label
                    htmlFor="lastName"
                    className="mb-2 block text-sm font-medium text-neutral-800"
                  >
                    Last name
                  </label>

                  <input
                    id="lastName"
                    name="lastName"
                    type="text"
                    autoComplete="family-name"
                    value={lastName}
                    onChange={(event) =>
                      setLastName(
                        event.target.value
                      )
                    }
                    disabled={loading}
                    required
                    className="w-full rounded-xl border border-neutral-300 bg-white px-4 py-3 text-sm text-neutral-950 outline-none transition placeholder:text-neutral-400 focus:border-neutral-950 focus:ring-2 focus:ring-neutral-950/10 disabled:cursor-not-allowed disabled:bg-neutral-100"
                    placeholder="Dela Cruz"
                  />
                </div>
              </div>


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
                  className="w-full rounded-xl border border-neutral-300 bg-white px-4 py-3 text-sm text-neutral-950 outline-none transition placeholder:text-neutral-400 focus:border-neutral-950 focus:ring-2 focus:ring-neutral-950/10 disabled:cursor-not-allowed disabled:bg-neutral-100"
                  placeholder="you@example.com"
                />
              </div>


              <div>
                <label
                  htmlFor="phone"
                  className="mb-2 block text-sm font-medium text-neutral-800"
                >
                  Mobile number
                </label>

                <input
                  id="phone"
                  name="phone"
                  type="tel"
                  autoComplete="tel"
                  inputMode="tel"
                  value={phone}
                  onChange={(event) =>
                    setPhone(
                      event.target.value
                    )
                  }
                  disabled={loading}
                  required
                  className="w-full rounded-xl border border-neutral-300 bg-white px-4 py-3 text-sm text-neutral-950 outline-none transition placeholder:text-neutral-400 focus:border-neutral-950 focus:ring-2 focus:ring-neutral-950/10 disabled:cursor-not-allowed disabled:bg-neutral-100"
                  placeholder="+63 917 123 4567"
                />
              </div>


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
                  autoComplete="new-password"
                  value={password}
                  onChange={(event) =>
                    setPassword(
                      event.target.value
                    )
                  }
                  disabled={loading}
                  required
                  minLength={10}
                  className="w-full rounded-xl border border-neutral-300 bg-white px-4 py-3 text-sm text-neutral-950 outline-none transition placeholder:text-neutral-400 focus:border-neutral-950 focus:ring-2 focus:ring-neutral-950/10 disabled:cursor-not-allowed disabled:bg-neutral-100"
                  placeholder="Minimum 10 characters"
                />

                <p className="mt-2 text-xs text-neutral-500">
                  Use at least 10 characters.
                </p>
              </div>


              <div>
                <label
                  htmlFor="confirmPassword"
                  className="mb-2 block text-sm font-medium text-neutral-800"
                >
                  Confirm password
                </label>

                <input
                  id="confirmPassword"
                  name="confirmPassword"
                  type="password"
                  autoComplete="new-password"
                  value={confirmPassword}
                  onChange={(event) =>
                    setConfirmPassword(
                      event.target.value
                    )
                  }
                  disabled={loading}
                  required
                  minLength={10}
                  className="w-full rounded-xl border border-neutral-300 bg-white px-4 py-3 text-sm text-neutral-950 outline-none transition placeholder:text-neutral-400 focus:border-neutral-950 focus:ring-2 focus:ring-neutral-950/10 disabled:cursor-not-allowed disabled:bg-neutral-100"
                  placeholder="Enter your password again"
                />
              </div>


              <button
                type="submit"
                disabled={loading}
                className="flex w-full items-center justify-center rounded-xl bg-neutral-950 px-5 py-3.5 text-sm font-semibold text-white transition hover:bg-neutral-800 disabled:cursor-not-allowed disabled:opacity-60"
              >
                {loading
                  ? "Creating account..."
                  : "Create account"}
              </button>
            </form>


            {/* Login */}

            <div className="mt-8 border-t border-neutral-200 pt-6 text-center">
              <p className="text-sm text-neutral-600">
                Already have an account?{" "}
                <Link
                  href="/account/login"
                  className="font-semibold text-neutral-950 underline underline-offset-4"
                >
                  Sign in
                </Link>
              </p>
            </div>


            <p className="mt-6 text-center text-xs leading-5 text-neutral-500">
              By creating an account, you
              agree to StoreFleet&apos;s
              marketplace terms and privacy
              practices.
            </p>
          </div>
        </div>
      </div>
    </main>
  );
}