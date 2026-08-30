import "server-only";


/*
|--------------------------------------------------------------------------
| Store Hours
|--------------------------------------------------------------------------
*/

export type StoreHoursDay = {
  day: number;
  day_name: string;
  is_open: boolean;
  opens_at: string | null;
  closes_at: string | null;
};


/*
|--------------------------------------------------------------------------
| Store Address
|--------------------------------------------------------------------------
*/

export type StoreAddress = {
  line_1: string;
  line_2: string;
  city: string;
  state: string;
  postcode: string;
  country: string;
  formatted: string;
};


/*
|--------------------------------------------------------------------------
| Store Branch
|--------------------------------------------------------------------------
*/

export type StoreBranch = {
  id: number;
  name: string;
  slug: string;

  is_primary: boolean;
  is_open_now: boolean;

  status:
    | "open"
    | "closed";

  address: StoreAddress;

  location: {
    latitude: number | null;
    longitude: number | null;
  };

  today: StoreHoursDay | null;
  hours: StoreHoursDay[];
};


/*
|--------------------------------------------------------------------------
| Public Store
|--------------------------------------------------------------------------
*/

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
| Public Product Types
|--------------------------------------------------------------------------
*/

export type PublicProductCategory = {
  id: number;
  name: string;
  slug: string;
};


export type PublicProductMerchant = {
  id: number;
  name: string;
  slug: string;
};


/*
|--------------------------------------------------------------------------
| Public Product Branch
|--------------------------------------------------------------------------
|
| This is product-specific branch data.
|
| Unlike StoreBranch, this includes stock information for this particular
| product. The marketplace returns one product with all available branches.
|
*/

export type PublicProductBranch = {
  id: number;

  name: string;
  slug: string;

  is_primary: boolean;
  is_active: boolean;

  address: string;

  location: {
    latitude: number | null;
    longitude: number | null;
  };

  available: boolean;
  in_stock: boolean;

  stock_quantity: number | null;

  stock_source:
    | "branch"
    | "woocommerce";
};


/*
|--------------------------------------------------------------------------
| Public Product
|--------------------------------------------------------------------------
*/

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

  merchant: PublicProductMerchant;

  /*
  |--------------------------------------------------------------------------
  | Default / Selected Branch
  |--------------------------------------------------------------------------
  |
  | branch_id + branch represent the current fulfillment branch.
  |
  | For marketplace results this is the default branch selected by WordPress.
  | The frontend may replace it with the nearest in-stock branch.
  |
  */

  branch_id: number | null;

  branch?: PublicProductBranch | null;

  /*
  |--------------------------------------------------------------------------
  | All Product Branches
  |--------------------------------------------------------------------------
  |
  | Every active merchant branch where this product is available.
  |
  */

  branches?: PublicProductBranch[];

  branch_count?: number;

  /*
  |--------------------------------------------------------------------------
  | Stock For Current branch_id
  |--------------------------------------------------------------------------
  */

  available: boolean;
  in_stock: boolean;

  stock_quantity: number | null;

  stock_source:
    | "branch"
    | "woocommerce";
};


/*
|--------------------------------------------------------------------------
| One Product Response
|--------------------------------------------------------------------------
*/

export type PublicProductResponse = {
  success: true;

  generated_at: string;
  timezone: string;

  branch: PublicProductBranch | null;

  branches: PublicProductBranch[];

  product: PublicProduct;
};


/*
|--------------------------------------------------------------------------
| Merchant Products Response
|--------------------------------------------------------------------------
*/

export type PublicProductsResponse = {
  success: true;

  generated_at: string;
  timezone: string;

  merchant: PublicProductMerchant;

  branch: PublicProductBranch | null;

  pagination: {
    page: number;
    per_page: number;
    count: number;

    merchant_published_total: number;
    merchant_total_pages: number;
  };

  products: PublicProduct[];
};


/*
|--------------------------------------------------------------------------
| Marketplace Products Response
|--------------------------------------------------------------------------
*/

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
  ).replace(
    /\/+$/,
    ""
  );
}


function getInternalApiKey() {
  const key =
    process.env
      .STOREFLEET_INTERNAL_API_KEY;


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


  return (
    await response.json()
  ) as PublicStoresResponse;
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


  if (
    response.status ===
    404
  ) {
    return null;
  }


  if (!response.ok) {
    throw new Error(
      `StoreFleet store API failed with status ${response.status}.`
    );
  }


  return (
    await response.json()
  ) as PublicStoreResponse;
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


  return (
    await response.json()
  ) as PublicProductsResponse;
}


/*
|--------------------------------------------------------------------------
| Marketplace Products
|--------------------------------------------------------------------------
|
| Each product is returned once.
|
| product.branches contains every active fulfillment branch where the product
| is available, including coordinates and stock data.
|
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


  return (
    await response.json()
  ) as PublicMarketplaceProductsResponse;
}


/*
|--------------------------------------------------------------------------
| One Product By Slug
|--------------------------------------------------------------------------
|
| Examples:
|
| getProductBySlug("barako-coffee-beans-250g")
|
| getProductBySlug(
|   "barako-coffee-beans-250g",
|   2
| )
|
| The second form requests a specific fulfillment branch.
|
*/

export async function getProductBySlug(
  slug: string,
  branchId?: number
): Promise<PublicProductResponse | null> {
  const params =
    new URLSearchParams();


  if (
    branchId &&
    branchId > 0
  ) {
    params.set(
      "branch_id",
      String(branchId)
    );
  }


  const query =
    params.toString();


  const response =
    await storefleetFetch(
      `/wp-json/storefleet/v1/products/by-slug/${encodeURIComponent(
        slug
      )}${query ? `?${query}` : ""}`
    );


  if (
    response.status ===
    404
  ) {
    return null;
  }


  if (!response.ok) {
    throw new Error(
      `StoreFleet product API failed with status ${response.status}.`
    );
  }


  return (
    await response.json()
  ) as PublicProductResponse;
}


/*
|--------------------------------------------------------------------------
| One Product By ID
|--------------------------------------------------------------------------
*/

export async function getProductById(
  productId: number,
  branchId?: number
): Promise<PublicProductResponse | null> {
  const params =
    new URLSearchParams();


  if (
    branchId &&
    branchId > 0
  ) {
    params.set(
      "branch_id",
      String(branchId)
    );
  }


  const query =
    params.toString();


  const response =
    await storefleetFetch(
      `/wp-json/storefleet/v1/products/${productId}${
        query
          ? `?${query}`
          : ""
      }`
    );


  if (
    response.status ===
    404
  ) {
    return null;
  }


  if (!response.ok) {
    throw new Error(
      `StoreFleet product API failed with status ${response.status}.`
    );
  }


  return (
    await response.json()
  ) as PublicProductResponse;
}


/*
|--------------------------------------------------------------------------
| Product Branch Helpers
|--------------------------------------------------------------------------
*/

/**
 * Returns the branch currently selected/defaulted by the API.
 */
export function getProductCurrentBranch(
  product: PublicProduct
): PublicProductBranch | null {
  if (
    product.branch
  ) {
    return product.branch;
  }


  if (
    product.branch_id &&
    product.branches
  ) {
    return (
      product.branches.find(
        (branch) =>
          branch.id ===
          product.branch_id
      ) ??
      null
    );
  }


  return null;
}


/**
 * Returns all active product branches that currently report in-stock.
 */
export function getProductInStockBranches(
  product: PublicProduct
) {
  return (
    product.branches ??
    []
  ).filter(
    (branch) =>
      branch.available &&
      branch.in_stock
  );
}


/**
 * Finds a specific product branch.
 */
export function getProductBranch(
  product: PublicProduct,
  branchId: number
) {
  return (
    product.branches ??
    []
  ).find(
    (branch) =>
      branch.id ===
      branchId
  ) ?? null;
}
