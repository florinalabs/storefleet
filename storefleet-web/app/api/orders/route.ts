import { NextResponse } from "next/server";

import {
  clearCustomerSessionCookie,
  getCustomerSessionToken,
  storefleetCustomerRequest,
} from "@/lib/storefleet/customer-session";


type OrderItem = {
  product_id: number;
  quantity: number;
};


type CreateOrderPayload = {
  idempotency_key?: string;
  address_id?: string;
  items?: OrderItem[];
};


/*
|--------------------------------------------------------------------------
| POST /api/orders
|--------------------------------------------------------------------------
*/

export async function POST(
  request: Request
) {
  try {
    /*
    |--------------------------------------------------------------------------
    | Customer Session
    |--------------------------------------------------------------------------
    */

    const sessionToken =
      await getCustomerSessionToken();


    if (!sessionToken) {
      return NextResponse.json(
        {
          success: false,
          message:
            "Customer authentication is required.",
        },
        {
          status: 401,
        }
      );
    }


    /*
    |--------------------------------------------------------------------------
    | Request Body
    |--------------------------------------------------------------------------
    */

    let body:
      CreateOrderPayload;


    try {
      body =
        (await request.json()) as CreateOrderPayload;
    } catch {
      return NextResponse.json(
        {
          success: false,
          message:
            "Invalid order request.",
        },
        {
          status: 400,
        }
      );
    }


    /*
    |--------------------------------------------------------------------------
    | Validate Idempotency Key
    |--------------------------------------------------------------------------
    */

    const idempotencyKey =
      body.idempotency_key?.trim() ??
      "";


    if (!idempotencyKey) {
      return NextResponse.json(
        {
          success: false,
          message:
            "An idempotency key is required.",
        },
        {
          status: 422,
        }
      );
    }


    if (
      idempotencyKey.length < 16 ||
      idempotencyKey.length > 128 ||
      !/^[A-Za-z0-9._:-]+$/.test(
        idempotencyKey
      )
    ) {
      return NextResponse.json(
        {
          success: false,
          message:
            "The idempotency key is invalid.",
        },
        {
          status: 422,
        }
      );
    }


    /*
    |--------------------------------------------------------------------------
    | Validate Address ID
    |--------------------------------------------------------------------------
    */

    const addressId =
      body.address_id?.trim() ??
      "";


    if (!addressId) {
      return NextResponse.json(
        {
          success: false,
          message:
            "A delivery address is required.",
        },
        {
          status: 422,
        }
      );
    }


    /*
    |--------------------------------------------------------------------------
    | Validate Cart Items
    |--------------------------------------------------------------------------
    */

    if (
      !Array.isArray(body.items) ||
      body.items.length === 0
    ) {
      return NextResponse.json(
        {
          success: false,
          message:
            "Your cart is empty.",
        },
        {
          status: 422,
        }
      );
    }


    if (body.items.length > 100) {
      return NextResponse.json(
        {
          success: false,
          message:
            "The cart contains too many items.",
        },
        {
          status: 422,
        }
      );
    }


    const items:
      OrderItem[] =
        [];


    for (const item of body.items) {
      const productId =
        Number(
          item?.product_id
        );


      const quantity =
        Number(
          item?.quantity
        );


      if (
        !Number.isInteger(productId) ||
        productId <= 0 ||
        !Number.isInteger(quantity) ||
        quantity <= 0 ||
        quantity > 99
      ) {
        return NextResponse.json(
          {
            success: false,
            message:
              "One or more cart items are invalid.",
          },
          {
            status: 422,
          }
        );
      }


      items.push({
        product_id:
          productId,

        quantity,
      });
    }


    /*
    |--------------------------------------------------------------------------
    | Forward To WordPress
    |--------------------------------------------------------------------------
    |
    | Prices are intentionally NOT forwarded.
    |
    | WordPress/WooCommerce resolves the actual product prices server-side.
    |
    */

    const response =
      await storefleetCustomerRequest(
        "/wp-json/storefleet/v1/orders",
        {
          method: "POST",

          sessionToken,

          body: {
            idempotency_key:
              idempotencyKey,

            address_id:
              addressId,

            items,
          },
        }
      );


    /*
    |--------------------------------------------------------------------------
    | Parse WordPress Response
    |--------------------------------------------------------------------------
    */

    const payload =
      await parseResponse(
        response
      );


    /*
    |--------------------------------------------------------------------------
    | Invalid Session
    |--------------------------------------------------------------------------
    */

    if (
      response.status === 401 ||
      response.status === 403
    ) {
      await clearCustomerSessionCookie();


      return NextResponse.json(
        payload,
        {
          status:
            response.status,
        }
      );
    }


    /*
    |--------------------------------------------------------------------------
    | WordPress Error
    |--------------------------------------------------------------------------
    */

    if (!response.ok) {
      return NextResponse.json(
        payload,
        {
          status:
            response.status,
        }
      );
    }


    /*
    |--------------------------------------------------------------------------
    | Success
    |--------------------------------------------------------------------------
    */

    return NextResponse.json(
      payload,
      {
        status:
          response.status,
      }
    );
  } catch (error) {
    console.error(
      "StoreFleet order creation proxy error:",
      error
    );


    return NextResponse.json(
      {
        success: false,
        message:
          "Unable to create the order. Please try again.",
      },
      {
        status: 500,
      }
    );
  }
}


/*
|--------------------------------------------------------------------------
| Safe Response Parser
|--------------------------------------------------------------------------
*/

async function parseResponse(
  response: Response
): Promise<Record<string, unknown>> {
  try {
    return (
      await response.json()
    ) as Record<string, unknown>;
  } catch {
    return {
      success: false,
      message:
        "StoreFleet returned an invalid response.",
    };
  }
}