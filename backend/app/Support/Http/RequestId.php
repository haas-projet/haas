<?php

namespace App\Support\Http;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class RequestId
{
    private const ATTRIBUTE = 'haas.request_id';

    public static function get(Request $request): string
    {
        $id = $request->attributes->get(self::ATTRIBUTE);

        if (! is_string($id)) {
            $id = (string) Str::uuid();
            $request->attributes->set(self::ATTRIBUTE, $id);
        }

        return $id;
    }
}
