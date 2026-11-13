<?php

namespace Database\Seeders;

use App\Models\Post;
use App\Support\Media;
use Illuminate\Database\Seeder;

class ContentSeeder extends Seeder
{
    public function run(): void
    {
        $posts = [
            ['slug' => 'the-art-of-dressing', 'title' => 'The Art of Dressing', 'category' => 'Editorial', 'excerpt' => 'On proportion, restraint and the quiet confidence of a wardrobe that knows exactly what it is.', 'image' => Media::unsplash('1571513722275-4b41940f54b8', 1200, '&h=1500'), 'read_time' => '6 min read', 'published_at' => '2026-09-02 10:00:00', 'is_featured' => true],
            ['slug' => 'modern-essentials', 'title' => 'Modern Essentials', 'category' => 'Style Notes', 'excerpt' => 'Twelve pieces, endlessly combined. A closer look at the foundation of the Élan wardrobe.', 'image' => Media::unsplash('1558769132-cb1aea458c5e', 1200, '&h=1500'), 'read_time' => '4 min read', 'published_at' => '2026-08-20 10:00:00'],
            ['slug' => 'the-signature-collection', 'title' => 'The Signature Collection', 'category' => 'Campaign', 'excerpt' => 'Behind the lens of our Autumn campaign — shot over three days in a converted textile mill.', 'image' => Media::unsplash('1524504388940-b1c1722653e1', 1200, '&h=1500'), 'read_time' => '8 min read', 'published_at' => '2026-08-12 10:00:00'],
        ];
        foreach ($posts as $post) {
            Post::updateOrCreate(['slug' => $post['slug'], 'template' => 'fashion'], $post + ['body' => '<p>'.$post['excerpt'].'</p><p>Full story coming soon.</p>']);
        }

        // Pages are seeded by PagesSeeder, as Fashion pages. This seeder used to add shared
        // copies of the same slugs too, which the storefront never showed (the Fashion page wins).
    }
}
