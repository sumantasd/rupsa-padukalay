<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Color;
use App\Models\HsnCode;
use App\Models\InventoryStock;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\ProductVariantSize;
use App\Models\Size;
use App\Models\Store;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DemoFootwearSeeder extends Seeder
{
    /**
     * Run the database seeds for RUPSA PADUKALAYA products.
     */
    public function run(): void
    {
        // 1. Ensure Default HSN Code
        $hsnCode = HsnCode::firstOrCreate(
            ['code' => '6403'],
            ['description' => 'Leather & Synthetic Footwear', 'default_gst_rate' => 12.00]
        );

        // 2. Ensure Store & Warehouse
        $warehouse = Warehouse::firstOrCreate(
            ['code' => 'WH-MAIN'],
            ['name' => 'RUPSA Central Depot', 'address' => '123 Market Road, Kolkata', 'is_active' => true]
        );

        $store = Store::firstOrCreate(
            ['code' => 'STR-MAIN'],
            [
                'name' => 'RUPSA PADUKALAYA Flagship Store',
                'phone' => '+91 98765 43210',
                'email' => 'store@rupsapadukalaya.com',
                'address' => '123 Footwear Market Road, College Street Hub, Kolkata 700001',
                'city' => 'Kolkata',
                'pincode' => '700001',
                'default_warehouse_id' => $warehouse->id,
                'is_active' => true,
            ]
        );

        // 3. Create Categories
        $categoriesData = [
            'Formal Shoes',
            'Casual Shoes',
            'Sports Shoes',
            'Sandals',
            'Slippers',
            'Boots',
        ];

        $categories = [];
        foreach ($categoriesData as $catName) {
            $categories[$catName] = Category::firstOrCreate(
                ['slug' => Str::slug($catName)],
                ['name' => $catName, 'is_visible_on_web' => true, 'is_active' => true]
            );
        }

        // 4. Create Brands
        $brandsData = [
            'RUPSA',
            'RUPSA Sport',
            'RUPSA Comfort',
            'RUPSA Classic',
        ];

        $brands = [];
        foreach ($brandsData as $brandName) {
            $brands[$brandName] = Brand::firstOrCreate(
                ['slug' => Str::slug($brandName)],
                ['name' => $brandName, 'is_featured_on_web' => true, 'is_active' => true]
            );
        }

        // 5. Create Sizes (UK/IND system 1 to 13)
        $sizes = [];
        for ($i = 1; $i <= 13; $i++) {
            $sizeStr = (string) $i;
            $sizes[$i] = Size::firstOrCreate(
                ['size_number' => $sizeStr, 'size_system' => 'UK/IND'],
                ['sort_order' => $i]
            );
        }

        // 6. Create Colors
        $colorsData = [
            'Black' => '#000000',
            'Red' => '#dc2626',
            'Black/Red' => '#1e1b4b',
            'Maroon' => '#800000',
            'Brown' => '#78350f',
            'White/Blue' => '#0284c7',
            'White/Pink' => '#ec4899',
            'Navy/White' => '#1e3a8a',
            'Tan' => '#d97706',
            'Blue/White' => '#2563eb',
        ];

        $colors = [];
        foreach ($colorsData as $cName => $cHex) {
            $colors[$cName] = Color::firstOrCreate(
                ['name' => $cName],
                ['code' => strtoupper(Str::slug($cName)), 'hex_code' => $cHex]
            );
        }

        // High Quality Footwear Images (CDN fallback / storage path)
        $productImages = [
            'RP-805' => 'https://images.unsplash.com/photo-1614252235316-8c857d38b5f4?auto=format&fit=crop&w=800&q=80',
            'RP-902' => 'https://images.unsplash.com/photo-1542291026-7eec264c27ff?auto=format&fit=crop&w=800&q=80',
            'RP-401' => 'https://images.unsplash.com/photo-1543163521-1bf539c55dd2?auto=format&fit=crop&w=800&q=80',
            'RP-105' => 'https://images.unsplash.com/photo-1608231387042-66d1773070a5?auto=format&fit=crop&w=800&q=80',
            'RP-610' => 'https://images.unsplash.com/photo-1525966222134-fcfa99b8ae77?auto=format&fit=crop&w=800&q=80',
            'RP-720' => 'https://images.unsplash.com/photo-1595950653106-6c9ebd614d3a?auto=format&fit=crop&w=800&q=80',
            'RP-830' => 'https://images.unsplash.com/photo-1606107557195-0e29a4b5b4aa?auto=format&fit=crop&w=800&q=80',
            'RP-450' => 'https://images.unsplash.com/photo-1533867617858-e7b97e060509?auto=format&fit=crop&w=800&q=80',
            'RP-520' => 'https://images.unsplash.com/photo-1560343776-97e7d202ff0e?auto=format&fit=crop&w=800&q=80',
            'RP-315' => 'https://images.unsplash.com/photo-1514989940723-e8e51635b782?auto=format&fit=crop&w=800&q=80',
        ];

        // 7. Define 10 Footwear Products
        $productsToSeed = [
            [
                'article_number' => 'RP-805',
                'name' => 'Executive Oxford Leather Shoe',
                'category' => 'Formal Shoes',
                'brand' => 'RUPSA Classic',
                'gender' => 'men',
                'color' => 'Black',
                'size_numbers' => [6, 7, 8, 9, 10],
                'mrp' => 2240.00,
                'selling_price' => 1792.00,
                'upper_material' => 'Genuine Calfskin Leather',
                'sole_material' => 'Cushion PU Sole',
                'description' => 'Premium genuine leather oxford shoe crafted for executive elegance and all-day arch comfort.',
            ],
            [
                'article_number' => 'RP-902',
                'name' => 'Pro Velocity Running Sports Shoe',
                'category' => 'Sports Shoes',
                'brand' => 'RUPSA Sport',
                'gender' => 'men',
                'color' => 'Black/Red',
                'size_numbers' => [6, 7, 8, 9, 10],
                'mrp' => 1899.00,
                'selling_price' => 1499.00,
                'upper_material' => 'Breathable Mesh',
                'sole_material' => 'Air Cushion Rubber',
                'description' => 'Lightweight high-performance running sneaker with shock-absorbing air cushion heel.',
            ],
            [
                'article_number' => 'RP-401',
                'name' => 'Traditional Ethnic Embroidered Sandal',
                'category' => 'Sandals',
                'brand' => 'RUPSA',
                'gender' => 'women',
                'color' => 'Maroon',
                'size_numbers' => [4, 5, 6, 7, 8],
                'mrp' => 1500.00,
                'selling_price' => 1250.00,
                'upper_material' => 'Velvet & Thread Embroidery',
                'sole_material' => 'Padded Leatherette',
                'description' => 'Handcrafted ethnic women sandal decorated with traditional zardozi embroidery.',
            ],
            [
                'article_number' => 'RP-105',
                'name' => 'Ortho Support Daily Comfort Slipper',
                'category' => 'Slippers',
                'brand' => 'RUPSA Comfort',
                'gender' => 'unisex',
                'color' => 'Brown',
                'size_numbers' => [6, 7, 8, 9, 10],
                'mrp' => 699.00,
                'selling_price' => 499.00,
                'upper_material' => 'Soft Synthetic Strap',
                'sole_material' => 'Orthopedic Memory Foam EVA',
                'description' => 'Doctor-recommended orthotic slip-on for heel pain relief and home walking support.',
            ],
            [
                'article_number' => 'RP-610',
                'name' => 'Urban Flex Casual Sneaker',
                'category' => 'Casual Shoes',
                'brand' => 'RUPSA',
                'gender' => 'men',
                'color' => 'White/Blue',
                'size_numbers' => [6, 7, 8, 9, 10],
                'mrp' => 1999.00,
                'selling_price' => 1599.00,
                'upper_material' => 'Synthetic Leather & Suede',
                'sole_material' => 'Vulcanized Rubber',
                'description' => 'Modern street-style low-top sneaker featuring anti-microbial breathable lining.',
            ],
            [
                'article_number' => 'RP-720',
                'name' => 'Women\'s Everyday Comfort Sneaker',
                'category' => 'Casual Shoes',
                'brand' => 'RUPSA Comfort',
                'gender' => 'women',
                'color' => 'White/Pink',
                'size_numbers' => [4, 5, 6, 7, 8],
                'mrp' => 1699.00,
                'selling_price' => 1299.00,
                'upper_material' => 'Breathable Knit Mesh',
                'sole_material' => 'Ultra-Light Phylon Sole',
                'description' => 'Feather-light women casual walking shoe designed for long standing hours.',
            ],
            [
                'article_number' => 'RP-830',
                'name' => 'Active Move Training Shoe',
                'category' => 'Sports Shoes',
                'brand' => 'RUPSA Sport',
                'gender' => 'men',
                'color' => 'Navy/White',
                'size_numbers' => [6, 7, 8, 9, 10],
                'mrp' => 2199.00,
                'selling_price' => 1749.00,
                'upper_material' => 'Engineered Mesh',
                'sole_material' => 'High-Traction Rubber',
                'description' => 'Multi-sport cross trainer with reinforced lateral support and anti-skid rubber grip.',
            ],
            [
                'article_number' => 'RP-450',
                'name' => 'Classic Leather Loafer',
                'category' => 'Formal Shoes',
                'brand' => 'RUPSA Classic',
                'gender' => 'men',
                'color' => 'Tan',
                'size_numbers' => [6, 7, 8, 9, 10],
                'mrp' => 2399.00,
                'selling_price' => 1899.00,
                'upper_material' => 'Full Grain Leather',
                'sole_material' => 'Durable TPR Sole',
                'description' => 'Hand-stitched tan leather penny loafer for smart casual and office wear.',
            ],
            [
                'article_number' => 'RP-520',
                'name' => 'Women\'s Comfort Daily Sandal',
                'category' => 'Sandals',
                'brand' => 'RUPSA Comfort',
                'gender' => 'women',
                'color' => 'Black',
                'size_numbers' => [4, 5, 6, 7, 8],
                'mrp' => 1299.00,
                'selling_price' => 999.00,
                'upper_material' => 'Soft Faux Leather',
                'sole_material' => 'Anti-Slip Cushion Sole',
                'description' => 'Ergonomic daily sandal for women with adjustable buckle strap and soft footbed.',
            ],
            [
                'article_number' => 'RP-315',
                'name' => 'Junior Active Kids Shoe',
                'category' => 'Sports Shoes',
                'brand' => 'RUPSA Sport',
                'gender' => 'boys',
                'color' => 'Blue/White',
                'size_numbers' => [1, 2, 3, 4, 5, 6],
                'mrp' => 1199.00,
                'selling_price' => 899.00,
                'upper_material' => 'Lightweight Mesh & Velcro',
                'sole_material' => 'Soft EVA Sole',
                'description' => 'Easy velcro closure kids active sneaker designed for daily play and school activities.',
            ],
        ];

        foreach ($productsToSeed as $pData) {
            $cat = $categories[$pData['category']];
            $brand = $brands[$pData['brand']];
            $color = $colors[$pData['color']];

            // Create Product
            $product = Product::updateOrCreate(
                ['article_number' => $pData['article_number']],
                [
                    'name' => $pData['name'],
                    'slug' => Str::slug($pData['article_number'].'-'.$pData['name']),
                    'brand_id' => $brand->id,
                    'category_id' => $cat->id,
                    'hsn_code_id' => $hsnCode->id,
                    'gender' => $pData['gender'],
                    'upper_material' => $pData['upper_material'],
                    'sole_material' => $pData['sole_material'],
                    'description' => $pData['description'],
                    'is_active' => true,
                    'is_visible_on_web' => true,
                    'is_featured_on_web' => true,
                ]
            );

            // Create Primary Product Image
            ProductImage::updateOrCreate(
                [
                    'product_id' => $product->id,
                    'is_primary' => true,
                ],
                [
                    'image_path' => $productImages[$pData['article_number']],
                    'alt_text' => $pData['name'],
                    'sort_order' => 1,
                    'is_active' => true,
                ]
            );

            // Create Product Color Variant
            $variant = ProductVariant::updateOrCreate(
                [
                    'product_id' => $product->id,
                    'color_id' => $color->id,
                ],
                [
                    'is_active' => true,
                ]
            );

            // Create Product Variant Sizes & Inventory Stocks (5 units per size)
            foreach ($pData['size_numbers'] as $sizeNum) {
                $sizeModel = $sizes[$sizeNum];
                $sku = "{$pData['article_number']}-".strtoupper(Str::slug($pData['color']))."-UK{$sizeNum}";
                $barcode = '890'.str_pad($product->id, 4, '0', STR_PAD_LEFT).str_pad($sizeNum, 2, '0', STR_PAD_LEFT);

                $variantSize = ProductVariantSize::updateOrCreate(
                    [
                        'product_variant_id' => $variant->id,
                        'size_id' => $sizeModel->id,
                    ],
                    [
                        'sku' => $sku,
                        'barcode' => $barcode,
                        'cost_price' => round($pData['selling_price'] * 0.6, 2),
                        'mrp' => $pData['mrp'],
                        'selling_price' => $pData['selling_price'],
                        'is_active' => true,
                    ]
                );

                InventoryStock::updateOrCreate(
                    [
                        'product_variant_size_id' => $variantSize->id,
                        'store_id' => $store->id,
                    ],
                    [
                        'warehouse_id' => $warehouse->id,
                        'stock_quantity' => 5,
                        'reorder_level' => 2,
                    ]
                );
            }
        }
    }
}
