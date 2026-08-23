import {
  NextRequest,
  NextResponse,
} from "next/server";

const WORDPRESS_URL =
  process.env.WORDPRESS_URL ||
  "http://localhost:8080";

const INTERNAL_KEY =
  process.env.STOREFLEET_INTERNAL_API_KEY;

export async function POST(
  request: NextRequest
) {
  if (!INTERNAL_KEY) {
    return NextResponse.json(
      {
        message:
          "StoreFleet registration service is not configured.",
      },
      {
        status: 500,
      }
    );
  }

  try {
    const formData =
      await request.formData();

    const response = await fetch(
      `${WORDPRESS_URL}/wp-json/storefleet/v1/merchants/register`,
      {
        method: "POST",

        headers: {
          "X-StoreFleet-Key":
            INTERNAL_KEY,
        },

        body: formData,

        cache: "no-store",
      }
    );

    const text =
      await response.text();

    let data;

    try {
      data = JSON.parse(text);
    } catch {
      data = {
        message:
          "Invalid response from StoreFleet backend.",
      };
    }

    return NextResponse.json(
      data,
      {
        status: response.status,
      }
    );
  } catch (error) {
    console.error(
      "Merchant registration:",
      error
    );

    return NextResponse.json(
      {
        message:
          "StoreFleet could not connect to the merchant service.",
      },
      {
        status: 500,
      }
    );
  }
}