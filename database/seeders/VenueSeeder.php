<?php

namespace Database\Seeders;

use App\Models\Venue;
use Illuminate\Database\Seeder;

class VenueSeeder extends Seeder
{
    public function run(): void
    {
        $venues = [
            // ── Malls ────────────────────────────────────────────────────────
            ['name' => 'Suria KLCC',                'address' => 'Kuala Lumpur City Centre, 50088 Kuala Lumpur',   'latitude' => 3.1579,  'longitude' => 101.7119, 'type' => 'mall',       'is_halal' => null,  'is_halal_verified' => false],
            ['name' => 'Mid Valley Megamall',        'address' => 'Lingkaran Syed Putra, 59200 Kuala Lumpur',       'latitude' => 3.1179,  'longitude' => 101.6767, 'type' => 'mall',       'is_halal' => null,  'is_halal_verified' => false],
            ['name' => 'Pavilion KL',                'address' => '168, Jalan Bukit Bintang, 55100 Kuala Lumpur',  'latitude' => 3.1488,  'longitude' => 101.7130, 'type' => 'mall',       'is_halal' => null,  'is_halal_verified' => false],
            ['name' => '1 Utama Shopping Centre',    'address' => '1, Lebuh Bandar Utama, 47800 Petaling Jaya',    'latitude' => 3.1513,  'longitude' => 101.6154, 'type' => 'mall',       'is_halal' => null,  'is_halal_verified' => false],
            ['name' => 'Sunway Pyramid',             'address' => '3, Jalan PJS 11/15, 47500 Subang Jaya',         'latitude' => 3.0732,  'longitude' => 101.6071, 'type' => 'mall',       'is_halal' => null,  'is_halal_verified' => false],

            // ── Restaurants (halal) ──────────────────────────────────────────
            ['name' => 'Nasi Kandar Pelita KLCC',   'address' => 'Jalan Ampang, 50450 Kuala Lumpur',              'latitude' => 3.1554,  'longitude' => 101.7161, 'type' => 'restaurant', 'is_halal' => true,  'is_halal_verified' => true],
            ['name' => 'Restoran Sri Paandi',        'address' => 'Jalan Travers, 50470 Kuala Lumpur',             'latitude' => 3.1290,  'longitude' => 101.6862, 'type' => 'restaurant', 'is_halal' => true,  'is_halal_verified' => true],
            ['name' => 'Village Park Restaurant',    'address' => 'Jalan SS 21/37, 47400 Damansara Uptown',        'latitude' => 3.1297,  'longitude' => 101.6456, 'type' => 'restaurant', 'is_halal' => true,  'is_halal_verified' => true],
            ['name' => 'Restoran Rebung Chef Ismail','address' => 'Hotel Bangsar, Jalan Bangsar, 59200 KL',        'latitude' => 3.1467,  'longitude' => 101.6724, 'type' => 'restaurant', 'is_halal' => true,  'is_halal_verified' => true],
            ['name' => 'Ayam Penyet Ria',            'address' => '168, Jalan Bukit Bintang, 55100 Kuala Lumpur',  'latitude' => 3.1488,  'longitude' => 101.7130, 'type' => 'restaurant', 'is_halal' => true,  'is_halal_verified' => true],

            // ── Cafes ────────────────────────────────────────────────────────
            ['name' => 'Breakfast Thieves KL',       'address' => 'Jalan Mesui, 50200 Kuala Lumpur',               'latitude' => 3.1441,  'longitude' => 101.7103, 'type' => 'cafe',       'is_halal' => true,  'is_halal_verified' => false],
            ['name' => 'Pulp by Papa Palheta',       'address' => 'Jalan Pudu Lama, 50200 Kuala Lumpur',           'latitude' => 3.1388,  'longitude' => 101.6865, 'type' => 'cafe',       'is_halal' => false, 'is_halal_verified' => false],
            ['name' => 'VCR Cafe',                   'address' => 'Jalan Galloway, 50150 Kuala Lumpur',            'latitude' => 3.1435,  'longitude' => 101.7108, 'type' => 'cafe',       'is_halal' => false, 'is_halal_verified' => false],
            ['name' => "Merchant's Lane",            'address' => 'Jalan Petaling, 50000 Kuala Lumpur',            'latitude' => 3.1468,  'longitude' => 101.6973, 'type' => 'cafe',       'is_halal' => true,  'is_halal_verified' => false],
            ['name' => 'Feeka Coffee Roasters',      'address' => 'Jalan Horley, 50200 Kuala Lumpur',              'latitude' => 3.1441,  'longitude' => 101.7056, 'type' => 'cafe',       'is_halal' => true,  'is_halal_verified' => false],

            // ── Parks ────────────────────────────────────────────────────────
            ['name' => 'KLCC Park',                  'address' => 'Kuala Lumpur City Centre, 50088 Kuala Lumpur',  'latitude' => 3.1579,  'longitude' => 101.7119, 'type' => 'park',       'is_halal' => null,  'is_halal_verified' => false],
            ['name' => 'Taman Botani Perdana',        'address' => 'Jalan Kebun Bunga, 50480 Kuala Lumpur',        'latitude' => 3.1445,  'longitude' => 101.6865, 'type' => 'park',       'is_halal' => null,  'is_halal_verified' => false],
            ['name' => 'Titiwangsa Lake Gardens',    'address' => 'Jalan Pahang, 53200 Kuala Lumpur',              'latitude' => 3.1800,  'longitude' => 101.7065, 'type' => 'park',       'is_halal' => null,  'is_halal_verified' => false],
            ['name' => 'Taman Jaya Park',            'address' => 'Jalan Kemajuan, 46200 Petaling Jaya',           'latitude' => 3.1078,  'longitude' => 101.6367, 'type' => 'park',       'is_halal' => null,  'is_halal_verified' => false],
            ['name' => 'Shah Alam Lake',             'address' => 'Persiaran Tasik, 40000 Shah Alam',              'latitude' => 3.0733,  'longitude' => 101.5185, 'type' => 'park',       'is_halal' => null,  'is_halal_verified' => false],

            // ── Beaches ──────────────────────────────────────────────────────
            ['name' => 'Pantai Morib',               'address' => 'Morib, 42700 Banting, Selangor',                'latitude' => 2.7375,  'longitude' => 101.4282, 'type' => 'beach',      'is_halal' => null,  'is_halal_verified' => false],
            ['name' => 'Pantai Remis',               'address' => 'Perak Tengah, 32700 Perak',                     'latitude' => 3.5912,  'longitude' => 101.0733, 'type' => 'beach',      'is_halal' => null,  'is_halal_verified' => false],
            ['name' => 'Pantai Kelanang',            'address' => 'Banting, 42700 Selangor',                       'latitude' => 2.8167,  'longitude' => 101.5333, 'type' => 'beach',      'is_halal' => null,  'is_halal_verified' => false],

            // ── Waterfalls ───────────────────────────────────────────────────
            ['name' => 'Kanching Rainforest Waterfall', 'address' => 'Rawang, 48000 Selangor',                     'latitude' => 3.2833,  'longitude' => 101.5833, 'type' => 'waterfall',  'is_halal' => null,  'is_halal_verified' => false],
            ['name' => 'Templer Park Waterfall',     'address' => 'Rawang, 48000 Selangor',                        'latitude' => 3.2756,  'longitude' => 101.5867, 'type' => 'waterfall',  'is_halal' => null,  'is_halal_verified' => false],
            ['name' => 'Sungai Congkak Waterfall',   'address' => 'Hulu Langat, 43100 Selangor',                   'latitude' => 3.2167,  'longitude' => 101.8667, 'type' => 'waterfall',  'is_halal' => null,  'is_halal_verified' => false],

            // ── Rivers ───────────────────────────────────────────────────────
            ['name' => 'Sungai Gabai',               'address' => 'Hulu Langat, 43100 Selangor',                   'latitude' => 3.0167,  'longitude' => 101.8833, 'type' => 'river',      'is_halal' => null,  'is_halal_verified' => false],
            ['name' => 'Sungai Chilling',            'address' => 'Kuala Kubu Bharu, 44000 Selangor',              'latitude' => 3.5500,  'longitude' => 101.7833, 'type' => 'river',      'is_halal' => null,  'is_halal_verified' => false],
            ['name' => 'Sungai Tekala',              'address' => 'Hulu Langat, 43100 Selangor',                   'latitude' => 3.0000,  'longitude' => 101.9000, 'type' => 'river',      'is_halal' => null,  'is_halal_verified' => false],
        ];

        foreach ($venues as $venue) {
            Venue::create($venue);
        }
    }
}
