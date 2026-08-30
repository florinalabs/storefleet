import Link from "next/link";

import type {
  StoreBranch,
  StoreHoursDay,
} from "@/lib/storefleet-api";


export default function StoreBranchSelector({
  branches,
  selectedBranchId,
  storeSlug,
}: {
  branches: StoreBranch[];
  selectedBranchId: number | null;
  storeSlug: string;
}) {
  const selectedBranch =
    branches.find(
      (branch) =>
        branch.id ===
        selectedBranchId
    ) ??
    branches[0] ??
    null;


  if (!selectedBranch) {
    return (
      <div className="rounded-2xl border border-dashed border-zinc-300 bg-white p-6 text-center">
        <h3 className="font-black">
          No active branches
        </h3>

        <p className="mt-1 text-sm text-zinc-500">
          This store does not have an active customer branch yet.
        </p>
      </div>
    );
  }


  return (
    <div className="space-y-4">

      {/* Compact Branch Selector */}

      <div className="flex gap-3 overflow-x-auto pb-1">

        {branches.map(
          (branch) => {
            const selected =
              branch.id ===
              selectedBranch.id;

            return (
              <Link
                key={branch.id}
                href={`/stores/${storeSlug}?branch=${branch.id}#products`}
                className={`min-w-[220px] flex-1 rounded-2xl border px-4 py-3 transition ${
                  selected
                    ? "border-violet-600 bg-violet-50"
                    : "border-zinc-200 bg-white hover:border-zinc-300"
                }`}
              >
                <div className="flex items-center justify-between gap-3">

                  <div className="min-w-0">

                    <div className="flex items-center gap-2">

                      <strong className="truncate text-sm text-zinc-950">
                        {branch.name}
                      </strong>

                      {branch.is_primary && (
                        <span className="shrink-0 rounded-full bg-violet-100 px-2 py-0.5 text-[9px] font-black uppercase tracking-wide text-violet-700">
                          Primary
                        </span>
                      )}

                    </div>

                    <p className="mt-1 truncate text-xs text-zinc-500">
                      {shortAddress(
                        branch
                      )}
                    </p>

                  </div>


                  <BranchStatus
                    branch={branch}
                  />

                </div>
              </Link>
            );
          }
        )}

      </div>


      {/* Selected Branch Summary */}

      <div className="rounded-2xl border border-zinc-200 bg-white">

        <div className="grid gap-4 p-5 lg:grid-cols-[1fr_auto] lg:items-center">

          <div className="min-w-0">

            <div className="flex flex-wrap items-center gap-2">

              <h3 className="text-xl font-black tracking-[-0.03em]">
                {selectedBranch.name}
              </h3>

              {selectedBranch.is_primary && (
                <span className="rounded-full bg-violet-100 px-2.5 py-1 text-[9px] font-black uppercase tracking-wide text-violet-700">
                  Primary branch
                </span>
              )}

              <BranchStatus
                branch={selectedBranch}
                large
              />

            </div>


            <p className="mt-2 max-w-3xl text-sm leading-6 text-zinc-500">
              {selectedBranch.address.formatted}
            </p>

          </div>


          <div className="flex flex-wrap gap-2">

            <div className="min-w-[150px] rounded-xl bg-zinc-50 px-4 py-3">

              <span className="block text-[10px] font-black uppercase tracking-[0.12em] text-zinc-400">
                Today
              </span>

              <strong className="mt-1 block text-sm text-zinc-950">
                {formatHours(
                  selectedBranch.today
                )}
              </strong>

            </div>


            {hasCoordinates(
              selectedBranch
            ) && (
              <a
                href={buildMapUrl(
                  selectedBranch
                )}
                className="min-w-[140px] rounded-xl bg-zinc-950 px-4 py-3 text-white transition hover:bg-zinc-800"
              >
                <span className="block text-[10px] font-black uppercase tracking-[0.12em] text-zinc-400">
                  Location
                </span>

                <strong className="mt-1 block text-sm">
                  View map →
                </strong>
              </a>
            )}

          </div>

        </div>


        {/* Collapsible Weekly Hours */}

        <details className="border-t border-zinc-100">

          <summary className="cursor-pointer list-none px-5 py-4 text-sm font-black text-zinc-800 hover:bg-zinc-50">
            <div className="flex items-center justify-between gap-4">
              <span>
                Weekly opening hours
              </span>

              <span className="text-xs font-semibold text-zinc-400">
                View
              </span>
            </div>
          </summary>


          <div className="grid gap-x-8 border-t border-zinc-100 px-5 py-3 sm:grid-cols-2">

            {selectedBranch.hours.map(
              (day) => (
                <div
                  key={day.day}
                  className="flex items-center justify-between gap-4 border-b border-zinc-100 py-2.5 text-sm last:border-b-0"
                >
                  <span className="font-medium text-zinc-600">
                    {day.day_name}
                  </span>

                  <span
                    className={
                      day.is_open
                        ? "font-bold text-zinc-950"
                        : "font-semibold text-zinc-400"
                    }
                  >
                    {formatHours(day)}
                  </span>
                </div>
              )
            )}

          </div>

        </details>

      </div>

    </div>
  );
}


function BranchStatus({
  branch,
  large = false,
}: {
  branch: StoreBranch;
  large?: boolean;
}) {
  return (
    <span
      className={`inline-flex shrink-0 items-center rounded-full font-black ${
        large
          ? "px-2.5 py-1 text-[10px]"
          : "px-2 py-1 text-[9px]"
      } ${
        branch.is_open_now
          ? "bg-emerald-100 text-emerald-700"
          : "bg-red-100 text-red-700"
      }`}
    >
      {branch.is_open_now
        ? "Open"
        : "Closed"}
    </span>
  );
}


function formatHours(
  day: StoreHoursDay | null
) {
  if (
    !day ||
    !day.is_open
  ) {
    return "Closed";
  }

  if (
    !day.opens_at ||
    !day.closes_at
  ) {
    return "Hours unavailable";
  }

  return `${formatTime(
    day.opens_at
  )} – ${formatTime(
    day.closes_at
  )}`;
}


function formatTime(
  value: string
) {
  const [hourValue, minute] =
    value.split(":");

  let hour =
    Number(hourValue);

  const suffix =
    hour >= 12
      ? "PM"
      : "AM";

  hour =
    hour % 12 || 12;

  return `${hour}:${minute} ${suffix}`;
}


function shortAddress(
  branch: StoreBranch
) {
  return (
    branch.address.city ||
    branch.address.formatted ||
    "Branch location"
  );
}


function hasCoordinates(
  branch: StoreBranch
) {
  return (
    branch.location.latitude !==
      null &&
    branch.location.longitude !==
      null
  );
}


function buildMapUrl(
  branch: StoreBranch
) {
  return `https://www.google.com/maps/search/?api=1&query=${branch.location.latitude},${branch.location.longitude}`;
}
