import "server-only";

export type StoreHoursDay = {
  day: number;
  day_name: string;
  is_open: boolean;
  opens_at: string | null;
  closes_at: string | null;
};

export type StoreAddress = {
  line_1: string;
  line_2: string;
  city: string;
  state: string;
  postcode: string;
  country: string;
  formatted: string;
};

export type StoreBranch = {
  id: number;
  name: string;
  slug: string;
  is_primary: boolean;
  is_open_now: boolean;
  status: "open" | "closed";
  address: StoreAddress;
  location: {
    latitude: number | null;
    longitude: number | null;
  };
  today: StoreHoursDay | null;
  hours: StoreHoursDay[];
};

export type PublicStore = {
  merchant_id: number;
  name: string;
  slug: string;
  description: string;
  logo_url: string;
  banner_url: string;
  phone: string;
  address: StoreAddress;
  primary_branch_id: number | null;
  branch_count: number;
};

export type PublicStoreResponse = {
  success: true;
  generated_at: string;
  timezone: string;
  store: PublicStore;
  branches: StoreBranch[];
};

export type PublicStoreListItem = {
  merchant_id: number;
  name: string;
  slug: string;
  description: string;
  logo_url: string;
  banner_url: string;
  phone: string;
  address: StoreAddress;
  verified: boolean;
  branch_count: number;
  open_branch_count: number;
  primary_branch_id: number | null;
  primary_branch: StoreBranch | null;
};

export type PublicStoresResponse = {
  success: true;
  generated_at: string;
  timezone: string;
  count: number;
  stores: PublicStoreListItem[];
};


/*
|--------------------------------------------------------------------------
| Public Products
|--------------------------------------------------------------------------
*/

export type PublicProductCategory = {
  id: number;
  name: string;
  slug: string;
};

export type PublicProduct = {
  id: number;
  slug: string;
  name: string;
  type: string;
  short_description: string;
  description: string;
  sku: string;
  currency: string;

  price: number;
  regular_price: number;
  on_sale: boolean;

  campaign: unknown | null;

  image_url: string;
  images: string[];

  categories: PublicProductCategory[];

  merchant: {
    id: number;
    name: string;
    slug: string;
  };

  branch_id: number | null;

  branch?: {
    id: number;
    name: string;
    slug: string;
    is_primary: boolean;
    is_active: boolean;
    address: string;
  } | null;

  available: boolean;
  in_stock: boolean;
  stock_quantity: number | null;
  stock_source: "branch" | "woocommerce";
};

export type PublicProductsResponse = {
  success: true;
  generated_at: string;
  timezone: string;

  merchant: {
    id: number;
    name: string;
    slug: string;
  };

  branch: {
    id: number;
    name: string;
    slug: string;
    is_primary: boolean;
    is_active: boolean;
    address: string;
  } | null;

  pagination: {
    page: number;
    per_page: number;
    count: number;
    merchant_published_total: number;
    merchant_total_pages: number;
  };

  products: PublicProduct[];
};




export type PublicMarketplaceProductsResponse = {
  success: true;
  generated_at: string;
  timezone: string;

  pagination: {
    page: number;
    per_page: number;
    count: number;
    published_total: number;
    total_pages: number;
  };

  products: PublicProduct[];
};


/*
|--------------------------------------------------------------------------
| Environment
|--------------------------------------------------------------------------
*/

function getWordPressUrl() {
  return (
    process.env.WORDPRESS_URL ??
    process.env.WORDPRESS_BASE_URL ??
    "http://localhost:8080"
  ).replace(/\/+$/, "");
}


function getInternalApiKey() {
  const key =
    process.env.STOREFLEET_INTERNAL_API_KEY;

  if (!key) {
    throw new Error(
      "STOREFLEET_INTERNAL_API_KEY is not configured in the Next.js server environment."
    );
  }

  return key;
}


/*
|--------------------------------------------------------------------------
| Internal Fetch
|--------------------------------------------------------------------------
*/

async function storefleetFetch(
  path: string
) {
  return fetch(
    `${getWordPressUrl()}${path}`,
    {
      headers: {
        "x-storefleet-key":
          getInternalApiKey(),
      },

      next: {
        revalidate: 60,
      },
    }
  );
}


/*
|--------------------------------------------------------------------------
| Stores
|--------------------------------------------------------------------------
*/

export async function getStores(): Promise<PublicStoresResponse> {
  const response =
    await storefleetFetch(
      "/wp-json/storefleet/v1/stores"
    );

  if (!response.ok) {
    throw new Error(
      `StoreFleet stores API failed with status ${response.status}.`
    );
  }

  return (await response.json()) as PublicStoresResponse;
}


/*
|--------------------------------------------------------------------------
| Store By Slug
|--------------------------------------------------------------------------
*/

export async function getStoreBySlug(
  slug: string
): Promise<PublicStoreResponse | null> {
  const response =
    await storefleetFetch(
      `/wp-json/storefleet/v1/stores/by-slug/${encodeURIComponent(
        slug
      )}`
    );

  if (response.status === 404) {
    return null;
  }

  if (!response.ok) {
    throw new Error(
      `StoreFleet store API failed with status ${response.status}.`
    );
  }

  return (await response.json()) as PublicStoreResponse;
}


/*
|--------------------------------------------------------------------------
| Store Products By Branch
|--------------------------------------------------------------------------
*/

export async function getStoreProducts(
  storeSlug: string,
  branchId: number,
  page = 1,
  perPage = 24
): Promise<PublicProductsResponse> {
  const params =
    new URLSearchParams({
      branch_id:
        String(branchId),

      page:
        String(page),

      per_page:
        String(perPage),
    });

  const response =
    await storefleetFetch(
      `/wp-json/storefleet/v1/stores/by-slug/${encodeURIComponent(
        storeSlug
      )}/products?${params.toString()}`
    );

  if (!response.ok) {
    throw new Error(
      `StoreFleet products API failed with status ${response.status}.`
    );
  }

  return (await response.json()) as PublicProductsResponse;
}


/*
|--------------------------------------------------------------------------
| Marketplace Products
|--------------------------------------------------------------------------
*/

export async function getProducts(
  page = 1,
  perPage = 100
): Promise<PublicMarketplaceProductsResponse> {
  const params =
    new URLSearchParams({
      page:
        String(page),

      per_page:
        String(perPage),
    });

  const response =
    await storefleetFetch(
      `/wp-json/storefleet/v1/products?${params.toString()}`
    );

  if (!response.ok) {
    throw new Error(
      `StoreFleet marketplace products API failed with status ${response.status}.`
    );
  }

  return (await response.json()) as PublicMarketplaceProductsResponse;
}
