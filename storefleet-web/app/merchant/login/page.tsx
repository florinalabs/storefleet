import type { Metadata } from "next";
import Link from "next/link";


export const metadata: Metadata = {
  title: "Merchant Login",
  description:
    "Sign in to your StoreFleet merchant account.",
};


const WORDPRESS_URL =
  process.env.WORDPRESS_URL ||
  "http://localhost:8080";


export default function MerchantLoginPage() {

  const loginUrl =
    `${WORDPRESS_URL}/wp-login.php`;

  const dashboardUrl =
    `${WORDPRESS_URL}/dashboard/`;


  return (
    <main className="min-h-[calc(100vh-72px)] bg-zinc-50">

      <div className="mx-auto grid min-h-[calc(100vh-72px)] w-full max-w-7xl lg:grid-cols-[0.9fr_1.1fr]">


        {/* Left Side */}

        <section className="hidden bg-violet-950 px-10 py-16 text-white lg:flex lg:flex-col lg:justify-between lg:px-16">

          <div>

            <Link
              href="/"
              className="text-2xl font-black tracking-[-0.05em]"
            >
              StoreFleet
            </Link>

          </div>


          <div className="max-w-lg">

            <span className="text-xs font-black uppercase tracking-[0.15em] text-violet-300">
              Merchant Portal
            </span>


            <h1 className="mt-5 text-5xl font-black leading-[0.95] tracking-[-0.055em] xl:text-6xl">
              Run your store
              from one place.
            </h1>


            <p className="mt-7 text-lg leading-8 text-violet-100/70">
              Manage products, orders,
              branches, staff, inventory,
              payments and delivery through
              StoreFleet.
            </p>


            <div className="mt-10 grid grid-cols-2 gap-3">

              <Feature
                number="01"
                title="Products"
              />

              <Feature
                number="02"
                title="Orders"
              />

              <Feature
                number="03"
                title="Branches"
              />

              <Feature
                number="04"
                title="Staff"
              />

            </div>

          </div>


          <p className="text-sm text-violet-200/50">
            StoreFleet Merchant
          </p>

        </section>


        {/* Login Side */}

        <section className="flex items-center justify-center px-5 py-14 sm:px-8 lg:px-14">

          <div className="w-full max-w-md">


            {/* Mobile Logo */}

            <Link
              href="/"
              className="mb-12 inline-block text-2xl font-black tracking-[-0.05em] lg:hidden"
            >
              StoreFleet
            </Link>


            {/* Heading */}

            <div>

              <span className="text-xs font-black uppercase tracking-[0.15em] text-violet-600">
                Merchant Login
              </span>


              <h1 className="mt-4 text-4xl font-black tracking-[-0.05em] sm:text-5xl">
                Welcome back.
              </h1>


              <p className="mt-4 leading-7 text-zinc-500">
                Sign in to manage your
                StoreFleet merchant account.
              </p>

            </div>


            {/* WordPress Login Form */}

            <form
              action={loginUrl}
              method="post"
              className="mt-9 space-y-5"
            >


              {/* Email / Username */}

              <div>

                <label
                  htmlFor="log"
                  className="mb-2 block text-sm font-bold"
                >
                  Email or username
                </label>


                <input
                  id="log"
                  name="log"
                  type="text"
                  autoComplete="username"
                  required
                  placeholder="merchant@example.com"
                  className="min-h-13 w-full rounded-xl border border-zinc-300 bg-white px-4 text-sm outline-none transition placeholder:text-zinc-400 focus:border-violet-600 focus:ring-4 focus:ring-violet-600/10"
                />

              </div>


              {/* Password */}

              <div>

                <div className="mb-2 flex items-center justify-between gap-4">

                  <label
                    htmlFor="pwd"
                    className="block text-sm font-bold"
                  >
                    Password
                  </label>


                  <a
                    href={`${WORDPRESS_URL}/wp-login.php?action=lostpassword`}
                    className="text-xs font-bold text-violet-600 transition hover:text-violet-800"
                  >
                    Forgot password?
                  </a>

                </div>


                <input
                  id="pwd"
                  name="pwd"
                  type="password"
                  autoComplete="current-password"
                  required
                  className="min-h-13 w-full rounded-xl border border-zinc-300 bg-white px-4 text-sm outline-none transition focus:border-violet-600 focus:ring-4 focus:ring-violet-600/10"
                />

              </div>


              {/* Remember Me */}

              <label className="flex cursor-pointer items-center gap-3">

                <input
                  type="checkbox"
                  name="rememberme"
                  value="forever"
                  className="h-4 w-4 rounded border-zinc-300 accent-violet-600"
                />


                <span className="text-sm text-zinc-600">
                  Keep me signed in
                </span>

              </label>


              {/* WordPress Redirect */}

              <input
                type="hidden"
                name="redirect_to"
                value={dashboardUrl}
              />

              <input
                type="hidden"
                name="testcookie"
                value="1"
              />


              {/* Submit */}

              <button
                type="submit"
                className="flex min-h-13 w-full items-center justify-center rounded-full bg-violet-600 px-7 font-bold text-white transition hover:bg-violet-700"
              >
                Sign in to StoreFleet
              </button>

            </form>


            {/* Register */}

            <div className="mt-8 border-t border-zinc-200 pt-7">

              <p className="text-center text-sm text-zinc-500">
                Don&apos;t have a merchant
                account?{" "}
                <Link
                  href="/merchant/register"
                  className="font-bold text-violet-600 transition hover:text-violet-800"
                >
                  Become a Merchant
                </Link>
              </p>

            </div>


            {/* Customer Link */}

            <div className="mt-5 text-center">

              <Link
                href="/"
                className="text-sm font-semibold text-zinc-400 transition hover:text-zinc-950"
              >
                ← Return to StoreFleet
              </Link>

            </div>


            {/* Security */}

            <div className="mt-10 rounded-2xl border border-zinc-200 bg-white p-4">

              <div className="flex gap-3">

                <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-violet-100 text-sm font-black text-violet-700">
                  ✓
                </div>


                <div>

                  <strong className="text-sm">
                    Secure merchant access
                  </strong>


                  <p className="mt-1 text-xs leading-5 text-zinc-500">
                    StoreFleet will never ask
                    for your password, banking
                    password, PIN or OTP
                    through chat or email.
                  </p>

                </div>

              </div>

            </div>

          </div>

        </section>

      </div>

    </main>
  );
}


/*
|--------------------------------------------------------------------------
| Feature
|--------------------------------------------------------------------------
*/

function Feature({
  number,
  title,
}: {
  number: string;
  title: string;
}) {

  return (
    <div className="rounded-2xl border border-white/10 bg-white/5 p-4">

      <span className="text-xs font-black text-violet-300/60">
        {number}
      </span>


      <strong className="mt-3 block text-sm">
        {title}
      </strong>

    </div>
  );
}