<?php

namespace App\class\term;

use App\Sage;
use WC_Product;

// like class WC_Product_Simple
class WC_Product_Egas extends WC_Product
{
    public function get_type()
    {
        return Sage::TOKEN;
    }
}
