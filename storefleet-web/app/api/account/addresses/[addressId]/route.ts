import { NextResponse } from "next/server";

import {
  clearCustomerSessionCookie,
  getCustomerSessionToken,
  storefleetCustomerRequest,
} from "@/lib/storefleet/customer-session";


type AddressPayload = {
  label?: string;
  first_name?: string;
  last_name?: string;
  phone?: string;
  address_1?: string;
  address_2?: string;
  barangay?: string;
  city?: string;
  province?: string;
  postcode?: string;
  country?: string;
  latitude?: number | null;
  longitude?: number | null;
  is_default?: boolean;
};


type RouteContext = {
  params: Promise<{
    addressId: string;
  }>;
};


/*
|--------------------------------------------------------------------------
| PATCH /api/account/addresses/[addressId]
|--------------------------------------------------------------------------
*/

export async function PATCH(
  request: Request,
  context: RouteContext
) {
  try {
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


    const { addressId } =
      await context.params;


    if (!addressId) {
      return NextResponse.json(
        {
          success: false,
          message:
            "Address ID is required.",
        },
        {
          status: 400,
        }
      );
    }


    const body =
      (await request.json()) as AddressPayload;


    const response =
      await storefleetCustomerRequest(
        `/wp-json/storefleet/v1/customers/addresses/${encodeURIComponent(
          addressId
        )}`,
        {
          method: "PATCH",
          body,
          sessionToken,
        }
      );


    const payload =
      await parseResponse(
        response
      );


    if (!response.ok) {
      if (
        response.status === 401 ||
        response.status === 403
      ) {
        await clearCustomerSessionCookie();
      }


      return NextResponse.json(
        payload,
        {
          status: response.status,
        }
      );
    }


    return NextResponse.json(
      payload,
      {
        status: response.status,
      }
    );
  } catch (error) {
    console.error(
      "StoreFleet customer address update error:",
      error
    );


    return NextResponse.json(
      {
        success: false,
        message:
          "Unable to update delivery address.",
      },
      {
        status: 500,
      }
    );
  }
}


/*
|--------------------------------------------------------------------------
| DELETE /api/account/addresses/[addressId]
|--------------------------------------------------------------------------
*/

export async function DELETE(
  _request: Request,
  context: RouteContext
) {
  try {
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


    const { addressId } =
      await context.params;


    if (!addressId) {
      return NextResponse.json(
        {
          success: false,
          message:
            "Address ID is required.",
        },
        {
          status: 400,
        }
      );
    }


    const response =
      await storefleetCustomerRequest(
        `/wp-json/storefleet/v1/customers/addresses/${encodeURIComponent(
          addressId
        )}`,
        {
          method: "DELETE",
          sessionToken,
        }
      );


    const payload =
      await parseResponse(
        response
      );


    if (!response.ok) {
      if (
        response.status === 401 ||
        response.status === 403
      ) {
        await clearCustomerSessionCookie();
      }


      return NextResponse.json(
        payload,
        {
          status: response.status,
        }
      );
    }


    return NextResponse.json(
      payload,
      {
        status: response.status,
      }
    );
  } catch (error) {
    console.error(
      "StoreFleet customer address delete error:",
      error
    );


    return NextResponse.json(
      {
        success: false,
        message:
          "Unable to delete delivery address.",
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