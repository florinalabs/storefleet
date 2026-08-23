export const WORDPRESS_URL =
  process.env.WORDPRESS_URL || "http://localhost:8080";

export const SITE_URL =
  process.env.NEXT_PUBLIC_SITE_URL || "http://localhost:3000";

export type StoreFleetProductImage = {
  id: number;
  src: string;
  thumbnail?: string;
  srcset?: string;
  sizes?: string;
  name?: string;
  alt?: string;
};

export type StoreFleetProductPrice = {
  price: string;
  regular_price: string;
  sale_price: string;
  currency_code: string;
  currency_symbol: string;
  currency_minor_unit: number;
  currency_decimal_separator: string;
  currency_thousand_separator: string;
  currency_prefix: string;
  currency_suffix: string;
};

export type StoreFleetProduct = {
  id: number;
  name: string;
  slug: string;
  permalink: string;

  summary?: string;
  short_description?: string;
  description?: string;

  prices: StoreFleetProductPrice;

  images: StoreFleetProductImage[];

  categories?: {
    id: number;
    name: string;
    slug: string;
  }[];

  is_in_stock?: boolean;
  average_rating?: string;
  review_count?: number;
};

export async function getProducts(
  limit = 12
): Promise<StoreFleetProduct[]> {
  try {
    const response = await fetch(
      `${WORDPRESS_URL}/wp-json/wc/store/v1/products?per_page=${limit}`,
      {
        next: {
          revalidate: 30,
        },
      }
    );

    if (!response.ok) {
      console.error(
        "StoreFleet product request failed:",
        response.status
      );

      return [];
    }

    return await response.json();
  } catch (error) {
    console.error(
      "StoreFleet could not connect to WordPress:",
      error
    );

    return [];
  }
}

export async function getProductBySlug(
  slug: string
): Promise<StoreFleetProduct | null> {
  try {
    const response = await fetch(
      `${WORDPRESS_URL}/wp-json/wc/store/v1/products?slug=${encodeURIComponent(
        slug
      )}`,
      {
        next: {
          revalidate: 30,
        },
      }
    );

    if (!response.ok) {
      return null;
    }

    const products: StoreFleetProduct[] =
      await response.json();

    return products[0] ?? null;
  } catch (error) {
    console.error(error);

    return null;
  }
}

export function formatProductPrice(
  product: StoreFleetProduct
) {
  const minorUnit =
    product.prices.currency_minor_unit ?? 2;

  const amount =
    Number(product.prices.price) /
    Math.pow(10, minorUnit);

  return new Intl.NumberFormat("en-PH", {
    style: "currency",
    currency:
      product.prices.currency_code || "PHP",
  }).format(amount);
}