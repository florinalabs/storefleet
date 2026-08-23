import Link from "next/link";
import { redirect } from "next/navigation";

import {
  getCustomerSessionToken,
  storefleetCustomerRequest,
} from "@/lib/storefleet/customer-session";


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
  customer?: Customer | null;
};


/*
|--------------------------------------------------------------------------
| Checkout
|--------------------------------------------------------------------------
|
| Checkout is customer-only.
|
| Visitors may browse and use the cart without signing in, but they must
| have a valid StoreFleet customer session before entering checkout.
|
*/

export default async function CheckoutPage() {
  /*
  |--------------------------------------------------------------------------
  | Require Customer Session
  |--------------------------------------------------------------------------
  */

  const sessionToken =
    await getCustomerSessionToken();


  if (!sessionToken) {
    redirect(
      "/account/login?next=/checkout"
    );
  }


  /*
  |--------------------------------------------------------------------------
  | Validate Session
  |--------------------------------------------------------------------------
  */

  const response =
    await storefleetCustomerRequest(
      "/wp-json/storefleet/v1/customers/me",
      {
        method: "GET",
        sessionToken,
      }
    );


  if (!response.ok) {
    redirect(
      "/account/login?next=/checkout"
    );
  }


  const payload =
    (await response.json()) as MeResponse;


  const customer =
    payload.customer;


  if (!customer) {
    redirect(
      "/account/login?next=/checkout"
    );
  }


  /*
  |--------------------------------------------------------------------------
  | Customer Display Name
  |--------------------------------------------------------------------------
  */

  const customerName =
    customer.first_name?.trim() ||
    customer.display_name?.trim() ||
    "Customer";


  return (
    <section className="min-h-[70vh] bg-zinc-50">

      <div className="mx-auto w-full max-w-7xl px-5 py-12 sm:px-6 lg:px-8">

        {/* Header */}

        <div className="mb-10">

          <p className="text-sm font-semibold text-violet-600">
            Checkout
          </p>


          <h1 className="mt-2 text-3xl font-black tracking-tight text-zinc-950 sm:text-4xl">
            Complete your order
          </h1>


          <p className="mt-3 max-w-2xl text-sm leading-6 text-zinc-500 sm:text-base">
            Hi, {customerName}. Review your
            delivery and payment information
            before placing your order.
          </p>

        </div>


        {/* Checkout Layout */}

        <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_380px]">

          {/* Checkout Details */}

          <div className="space-y-6">

            {/* Customer */}

            <CheckoutSection
              number="1"
              title="Customer"
            >

              <div className="grid gap-6 sm:grid-cols-2">

                <CheckoutField
                  label="Name"
                  value={
                    customer.display_name ||
                    [
                      customer.first_name,
                      customer.last_name,
                    ]
                      .filter(Boolean)
                      .join(" ") ||
                    "Customer"
                  }
                />


                <CheckoutField
                  label="Email"
                  value={
                    customer.email ||
                    "Not available"
                  }
                />


                <CheckoutField
                  label="Mobile number"
                  value={
                    customer.phone ||
                    "Not available"
                  }
                />

              </div>

            </CheckoutSection>


            {/* Delivery Address */}

            <CheckoutSection
              number="2"
              title="Delivery Address"
            >

              <div className="rounded-2xl border border-dashed border-zinc-300 bg-zinc-50 px-5 py-8">

                <p className="text-sm font-semibold text-zinc-900">
                  Delivery address
                </p>


                <p className="mt-2 text-sm leading-6 text-zinc-500">
                  Customer address selection
                  will be connected to StoreFleet
                  address management.
                </p>


                <span className="mt-4 inline-flex rounded-full bg-zinc-200 px-3 py-1 text-xs font-semibold text-zinc-600">
                  Coming next
                </span>

              </div>

            </CheckoutSection>


            {/* Delivery */}

            <CheckoutSection
              number="3"
              title="Delivery"
            >

              <div className="rounded-2xl border border-dashed border-zinc-300 bg-zinc-50 px-5 py-8">

                <p className="text-sm font-semibold text-zinc-900">
                  Delivery quotation
                </p>


                <p className="mt-2 text-sm leading-6 text-zinc-500">
                  Lalamove delivery options and
                  quotation will appear here.
                </p>


                <span className="mt-4 inline-flex rounded-full bg-zinc-200 px-3 py-1 text-xs font-semibold text-zinc-600">
                  Coming soon
                </span>

              </div>

            </CheckoutSection>


            {/* Payment */}

            <CheckoutSection
              number="4"
              title="Payment"
            >

              <div className="rounded-2xl border border-dashed border-zinc-300 bg-zinc-50 px-5 py-8">

                <p className="text-sm font-semibold text-zinc-900">
                  Online payment
                </p>


                <p className="mt-2 text-sm leading-6 text-zinc-500">
                  Secure online payment through
                  StoreFleet checkout will appear
                  here.
                </p>


                <span className="mt-4 inline-flex rounded-full bg-zinc-200 px-3 py-1 text-xs font-semibold text-zinc-600">
                  Coming soon
                </span>

              </div>

            </CheckoutSection>

          </div>


          {/* Order Summary */}

          <aside>

            <div className="sticky top-24 rounded-3xl border border-zinc-200 bg-white p-6 shadow-sm">

              <h2 className="text-lg font-bold text-zinc-950">
                Order Summary
              </h2>


              <p className="mt-2 text-sm leading-6 text-zinc-500">
                Your cart items and checkout
                totals will appear here.
              </p>


              <div className="my-6 border-t border-zinc-200" />


              <div className="space-y-4">

                <SummaryRow
                  label="Subtotal"
                  value="—"
                />


                <SummaryRow
                  label="Delivery"
                  value="—"
                />

              </div>


              <div className="my-6 border-t border-zinc-200" />


              <div className="flex items-center justify-between">

                <span className="font-bold text-zinc-950">
                  Total
                </span>


                <span className="text-lg font-black text-zinc-950">
                  —
                </span>

              </div>


              <button
                type="button"
                disabled
                className="mt-6 flex w-full cursor-not-allowed items-center justify-center rounded-xl bg-zinc-950 px-5 py-3.5 text-sm font-semibold text-white opacity-40"
              >
                Place Order
              </button>


              <p className="mt-3 text-center text-xs leading-5 text-zinc-500">
                Order placement will be enabled
                when checkout integration is
                complete.
              </p>


              <Link
                href="/cart"
                className="mt-5 flex items-center justify-center text-sm font-semibold text-zinc-600 transition hover:text-violet-600"
              >
                ← Return to cart
              </Link>

            </div>

          </aside>

        </div>

      </div>

    </section>
  );
}


/*
|--------------------------------------------------------------------------
| Checkout Section
|--------------------------------------------------------------------------
*/

function CheckoutSection({
  number,
  title,
  children,
}: {
  number: string;
  title: string;
  children: React.ReactNode;
}) {
  return (
    <div className="rounded-3xl border border-zinc-200 bg-white p-6 shadow-sm sm:p-8">

      <div className="mb-6 flex items-center gap-3">

        <span className="flex h-8 w-8 items-center justify-center rounded-full bg-zinc-950 text-xs font-bold text-white">
          {number}
        </span>


        <h2 className="text-lg font-bold text-zinc-950">
          {title}
        </h2>

      </div>


      {children}

    </div>
  );
}


/*
|--------------------------------------------------------------------------
| Checkout Field
|--------------------------------------------------------------------------
*/

function CheckoutField({
  label,
  value,
}: {
  label: string;
  value: string;
}) {
  return (
    <div>

      <p className="text-xs font-semibold uppercase tracking-wide text-zinc-400">
        {label}
      </p>


      <p className="mt-2 break-words text-sm font-semibold text-zinc-900">
        {value}
      </p>

    </div>
  );
}


/*
|--------------------------------------------------------------------------
| Summary Row
|--------------------------------------------------------------------------
*/

function SummaryRow({
  label,
  value,
}: {
  label: string;
  value: string;
}) {
  return (
    <div className="flex items-center justify-between text-sm">

      <span className="text-zinc-500">
        {label}
      </span>


      <span className="font-semibold text-zinc-900">
        {value}
      </span>

    </div>
  );
}