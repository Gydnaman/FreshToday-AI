<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
// use App\Models\Product; // Uncomment when DB is connected

class ProductController extends Controller
{
    public function index()
    {
        // Mock data to replace React state for now.
        // Replace with $products = Product::all(); once MySQL PDO connection is resolved
        $products = [
            (object) [
                'id' => 1, 
                'name' => 'Organic Kale Bundle', 
                'price' => 38, 
                'description' => 'Fresh kale from local Hong Kong farms', 
                'image' => 'https://images.unsplash.com/photo-1576045057995-568f588f82fb?w=500&q=80', 
                'carbonFootprint' => '0.12kg CO2e'
            ],
            (object) [
                'id' => 2, 
                'name' => 'Seasonal Fruit Box', 
                'price' => 88, 
                'description' => 'Mixed seasonal fruits sourced within 50km radius', 
                'image' => 'https://images.unsplash.com/photo-1610832958506-aa56368176cf?w=500&q=80', 
                'carbonFootprint' => '0.35kg CO2e'
            ],
            (object) [
                'id' => 3, 
                'name' => 'Free-Range Eggs', 
                'price' => 28, 
                'description' => '12 pasture-raised eggs from ethical farms', 
                'image' => 'https://images.unsplash.com/photo-1587486913049-53fc88980cfc?w=500&q=80', 
                'carbonFootprint' => '0.08kg CO2e'
            ],
            (object) [
                'id' => 4, 
                'name' => 'Herb Garden Kit', 
                'price' => 65, 
                'description' => 'Grow your own herbs with this sustainable kit', 
                'image' => 'https://images.unsplash.com/photo-1595085350993-fb344d41d92d?w=500&q=80', 
                'carbonFootprint' => '0.21kg CO2e'
            ]
        ];

        return view('catalog', compact('products'));
    }
}
