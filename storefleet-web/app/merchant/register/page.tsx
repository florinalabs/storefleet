"use client";

import Link from "next/link";
import {
  FormEvent,
  useState,
} from "react";

type RegistrationResult = {
  success?: boolean;
  message?: string;
  verification_status?: string;
};

export default function MerchantRegisterPage() {
  const [hasPermit, setHasPermit] =
    useState(false);

  const [loading, setLoading] =
    useState(false);

  const [error, setError] =
    useState("");

  const [success, setSuccess] =
    useState<RegistrationResult | null>(
      null
    );

  async function handleSubmit(
    event: FormEvent<HTMLFormElement>
  ) {
    event.preventDefault();

    setLoading(true);
    setError("");
    setSuccess(null);

    const form =
      event.currentTarget;

    const formData =
      new FormData(form);

    formData.set(
      "has_business_permit",
      hasPermit ? "1" : "0"
    );

    try {
      const response = await fetch(
        "/api/merchant/register",
        {
          method: "POST",
          body: formData,
        }
      );

      const data =
        await response.json();

      if (!response.ok) {
        setError(
          data.message ||
            "Registration could not be completed."
        );

        return;
      }

      setSuccess(data);

      form.reset();

      setHasPermit(false);
    } catch {
      setError(
        "StoreFleet could not process your registration."
      );
    } finally {
      setLoading(false);
    }
  }

  if (success?.success) {
    return (
      <main className="min-h-[75vh] bg-zinc-50 py-16 sm:py-24">
        <div className="mx-auto max-w-2xl px-5 sm:px-6">
          <div className="rounded-3xl border border-zinc-200 bg-white p-8 shadow-sm sm:p-12">
            <div className="flex h-14 w-14 items-center justify-center rounded-full bg-emerald-100 text-2xl text-emerald-700">
              ✓
            </div>

            <h1 className="mt-7 text-4xl font-black tracking-[-0.05em]">
              Registration received.
            </h1>

            <p className="mt-5 leading-7 text-zinc-600">
              Your StoreFleet merchant
              account has been created and
              is waiting for approval.
            </p>

            {success.verification_status ===
            "pending_review" ? (
              <div className="mt-8 rounded-2xl border border-amber-200 bg-amber-50 p-5">
                <strong className="text-amber-900">
                  Business permit submitted
                </strong>

                <p className="mt-2 text-sm leading-6 text-amber-800">
                  Your permit will be reviewed
                  by StoreFleet. The Verified
                  badge will only appear after
                  approval.
                </p>
              </div>
            ) : (
              <div className="mt-8 rounded-2xl border border-zinc-200 bg-zinc-50 p-5">
                <strong>
                  Currently Unverified
                </strong>

                <p className="mt-2 text-sm leading-6 text-zinc-600">
                  You registered without a
                  business permit. You can
                  still continue merchant
                  onboarding, but your store
                  will not display the
                  StoreFleet Verified badge.
                  You can submit a permit
                  later.
                </p>
              </div>
            )}

            <div className="mt-8 flex flex-wrap gap-3">
              <Link
                href="/merchant/login"
                className="inline-flex min-h-12 items-center justify-center rounded-full bg-violet-600 px-6 text-sm font-bold text-white transition hover:bg-violet-700"
              >
                Merchant Login
              </Link>

              <Link
                href="/"
                className="inline-flex min-h-12 items-center justify-center rounded-full border border-zinc-300 px-6 text-sm font-bold"
              >
                Return Home
              </Link>
            </div>
          </div>
        </div>
      </main>
    );
  }

  return (
    <main className="bg-zinc-50 py-14 sm:py-20">
      <div className="mx-auto grid w-full max-w-7xl gap-12 px-5 sm:px-6 lg:grid-cols-[0.7fr_1.3fr] lg:px-8">

        {/* Left */}

        <div className="lg:sticky lg:top-28 lg:self-start">
          <span className="text-xs font-black uppercase tracking-[0.15em] text-violet-600">
            StoreFleet Merchants
          </span>

          <h1 className="mt-5 text-5xl font-black leading-[0.95] tracking-[-0.06em] sm:text-6xl">
            Start selling
            with StoreFleet.
          </h1>

          <p className="mt-7 max-w-lg text-lg leading-8 text-zinc-600">
            Create your merchant account,
            manage products, branches, staff,
            orders and delivery from one
            platform.
          </p>

          <div className="mt-10 space-y-6">
            <Benefit
              number="01"
              title="Business permit optional"
              text="You may register even if you do not currently have a business permit."
            />

            <Benefit
              number="02"
              title="StoreFleet Verified"
              text="Submit valid business documents for review to qualify for the StoreFleet Verified badge."
            />

            <Benefit
              number="03"
              title="Multi-branch ready"
              text="Manage multiple store locations, employees and branch inventory."
            />
          </div>
        </div>

        {/* Form */}

        <div className="rounded-3xl border border-zinc-200 bg-white p-6 shadow-sm sm:p-10">
          <div>
            <h2 className="text-3xl font-black tracking-[-0.04em]">
              Merchant registration
            </h2>

            <p className="mt-2 text-sm leading-6 text-zinc-500">
              Tell us about you and your
              business.
            </p>
          </div>

          {error && (
            <div className="mt-7 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">
              {error}
            </div>
          )}

          <form
            onSubmit={handleSubmit}
            className="mt-8 space-y-10"
          >
            {/* Owner */}

            <FormSection
              title="Owner information"
              description="Information about the person responsible for the merchant account."
            >
              <div className="grid gap-5 sm:grid-cols-2">
                <Field
                  label="First name"
                  name="first_name"
                  required
                />

                <Field
                  label="Last name"
                  name="last_name"
                  required
                />

                <Field
                  label="Email address"
                  name="email"
                  type="email"
                  required
                />

                <Field
                  label="Mobile number"
                  name="phone"
                  type="tel"
                  placeholder="09XXXXXXXXX"
                  required
                />

                <div className="sm:col-span-2">
                  <Field
                    label="Password"
                    name="password"
                    type="password"
                    minLength={10}
                    required
                  />

                  <p className="mt-2 text-xs text-zinc-500">
                    Minimum 10 characters.
                  </p>
                </div>
              </div>
            </FormSection>

            {/* Business */}

            <FormSection
              title="Business information"
              description="This information will be associated with your StoreFleet merchant account."
            >
              <div className="grid gap-5 sm:grid-cols-2">
                <div className="sm:col-span-2">
                  <Field
                    label="Business / Store name"
                    name="business_name"
                    placeholder="ABC Grocery"
                    required
                  />
                </div>

                <SelectField
                  label="Business type"
                  name="business_type"
                  required
                  options={[
                    [
                      "",
                      "Select business type",
                    ],
                    [
                      "sole_proprietor",
                      "Sole Proprietor",
                    ],
                    [
                      "partnership",
                      "Partnership",
                    ],
                    [
                      "corporation",
                      "Corporation",
                    ],
                    [
                      "informal_seller",
                      "Individual / Informal Seller",
                    ],
                    [
                      "other",
                      "Other",
                    ],
                  ]}
                />

                <Field
                  label="Business phone"
                  name="business_phone"
                  type="tel"
                />

                <div className="sm:col-span-2">
                  <Field
                    label="Address"
                    name="address_line_1"
                    required
                  />
                </div>

                <Field
                  label="City"
                  name="city"
                  required
                />

                <Field
                  label="Province / State"
                  name="state"
                  required
                />

                <Field
                  label="Postal code"
                  name="postcode"
                />
              </div>
            </FormSection>

            {/* Permit */}

            <FormSection
              title="Business verification"
              description="A business permit is optional during registration."
            >
              <div className="grid gap-4 sm:grid-cols-2">
                <button
                  type="button"
                  onClick={() =>
                    setHasPermit(false)
                  }
                  className={`rounded-2xl border p-5 text-left transition ${
                    !hasPermit
                      ? "border-violet-600 bg-violet-50 ring-2 ring-violet-600/10"
                      : "border-zinc-200 hover:border-zinc-300"
                  }`}
                >
                  <strong className="block">
                    I don&apos;t have a
                    business permit
                  </strong>

                  <span className="mt-2 block text-sm leading-6 text-zinc-500">
                    You can register, but your
                    store will remain
                    Unverified.
                  </span>
                </button>

                <button
                  type="button"
                  onClick={() =>
                    setHasPermit(true)
                  }
                  className={`rounded-2xl border p-5 text-left transition ${
                    hasPermit
                      ? "border-violet-600 bg-violet-50 ring-2 ring-violet-600/10"
                      : "border-zinc-200 hover:border-zinc-300"
                  }`}
                >
                  <strong className="block">
                    I have a business permit
                  </strong>

                  <span className="mt-2 block text-sm leading-6 text-zinc-500">
                    Submit it for StoreFleet
                    verification review.
                  </span>
                </button>
              </div>

              {hasPermit && (
                <div className="mt-6 rounded-2xl border border-zinc-200 bg-zinc-50 p-5">
                  <div className="grid gap-5">
                    <Field
                      label="Business permit number"
                      name="business_permit_number"
                      required={hasPermit}
                    />

                    <div>
                      <label
                        htmlFor="business_permit_file"
                        className="mb-2 block text-sm font-bold"
                      >
                        Business permit file
                      </label>

                      <input
                        id="business_permit_file"
                        name="business_permit_file"
                        type="file"
                        accept=".pdf,.jpg,.jpeg,.png"
                        required={hasPermit}
                        className="block w-full rounded-xl border border-zinc-300 bg-white p-3 text-sm file:mr-4 file:rounded-lg file:border-0 file:bg-violet-100 file:px-4 file:py-2 file:font-bold file:text-violet-700"
                      />

                      <p className="mt-2 text-xs leading-5 text-zinc-500">
                        PDF, JPG or PNG. Maximum
                        10 MB.
                      </p>
                    </div>
                  </div>
                </div>
              )}

              {!hasPermit && (
                <div className="mt-6 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm leading-6 text-amber-900">
                  Your merchant account can
                  still be created. Your store
                  will display as
                  <strong> Unverified</strong>{" "}
                  until approved business
                  documents are submitted.
                </div>
              )}
            </FormSection>

            {/* Terms */}

            <label className="flex items-start gap-3">
              <input
                type="checkbox"
                name="terms"
                value="1"
                required
                className="mt-1 h-4 w-4 rounded border-zinc-300 accent-violet-600"
              />

              <span className="text-sm leading-6 text-zinc-600">
                I confirm the information I
                provided is accurate and agree
                to the StoreFleet merchant
                terms and verification process.
              </span>
            </label>

            <button
              type="submit"
              disabled={loading}
              className="inline-flex min-h-13 w-full items-center justify-center rounded-full bg-violet-600 px-7 font-bold text-white transition hover:bg-violet-700 disabled:cursor-not-allowed disabled:opacity-60 sm:w-auto"
            >
              {loading
                ? "Creating merchant account..."
                : "Create Merchant Account"}
            </button>

            <p className="text-sm text-zinc-500">
              Already registered?{" "}
              <Link
                href="/merchant/login"
                className="font-bold text-violet-600"
              >
                Merchant login
              </Link>
            </p>
          </form>
        </div>
      </div>
    </main>
  );
}


function FormSection({
  title,
  description,
  children,
}: {
  title: string;
  description: string;
  children: React.ReactNode;
}) {
  return (
    <section>
      <div className="mb-5 border-b border-zinc-200 pb-4">
        <h3 className="text-lg font-black">
          {title}
        </h3>

        <p className="mt-1 text-sm leading-6 text-zinc-500">
          {description}
        </p>
      </div>

      {children}
    </section>
  );
}


function Field({
  label,
  name,
  type = "text",
  placeholder,
  required = false,
  minLength,
}: {
  label: string;
  name: string;
  type?: string;
  placeholder?: string;
  required?: boolean;
  minLength?: number;
}) {
  return (
    <div>
      <label
        htmlFor={name}
        className="mb-2 block text-sm font-bold"
      >
        {label}

        {required && (
          <span className="text-red-500">
            {" "}
            *
          </span>
        )}
      </label>

      <input
        id={name}
        name={name}
        type={type}
        placeholder={placeholder}
        required={required}
        minLength={minLength}
        className="min-h-12 w-full rounded-xl border border-zinc-300 bg-white px-4 text-sm outline-none transition focus:border-violet-600 focus:ring-4 focus:ring-violet-600/10"
      />
    </div>
  );
}


function SelectField({
  label,
  name,
  options,
  required = false,
}: {
  label: string;
  name: string;
  required?: boolean;
  options: [string, string][];
}) {
  return (
    <div>
      <label
        htmlFor={name}
        className="mb-2 block text-sm font-bold"
      >
        {label}
      </label>

      <select
        id={name}
        name={name}
        required={required}
        className="min-h-12 w-full rounded-xl border border-zinc-300 bg-white px-4 text-sm outline-none transition focus:border-violet-600 focus:ring-4 focus:ring-violet-600/10"
      >
        {options.map(
          ([value, label]) => (
            <option
              key={value}
              value={value}
            >
              {label}
            </option>
          )
        )}
      </select>
    </div>
  );
}


function Benefit({
  number,
  title,
  text,
}: {
  number: string;
  title: string;
  text: string;
}) {
  return (
    <div className="flex gap-4">
      <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-violet-100 text-xs font-black text-violet-700">
        {number}
      </span>

      <div>
        <strong>
          {title}
        </strong>

        <p className="mt-1 text-sm leading-6 text-zinc-500">
          {text}
        </p>
      </div>
    </div>
  );
}