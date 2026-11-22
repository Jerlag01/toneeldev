<?php

namespace Database\Seeders;

use App\Models\Page;
use App\Models\Product;
use App\Models\Production;
use App\Models\ShopCategory;
use App\Models\ShopTable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Moves the story to /story, builds the new home page (productions), adds the past plays from the story,
 * and copies the menu and tables of the old bestel.vrolijktoneelinitiatief.be. Safe to run again.
 */
class ProductionsAndShopSeeder extends Seeder
{
    public function run(): void
    {
        $this->moveStory();
        $this->productions();
        $this->home();
        $this->shop();
    }

    private function moveStory(): void
    {
        $home = Page::query()->where('slug', Page::HOME_SLUG)->first();

        if ($home && $home->hasBlock('chapter') && ! Page::query()->where('slug', 'story')->exists()) {
            $home->update(['slug' => 'story', 'title' => 'Ons verhaal', 'show_in_menu' => true, 'menu_order' => 10]);
        }
    }

    private function home(): void
    {
        if (Page::query()->where('slug', Page::HOME_SLUG)->exists()) {
            return;
        }

        Page::query()->create([
            'title' => 'Home',
            'slug' => Page::HOME_SLUG,
            'is_published' => true,
            'show_in_menu' => false,
            'menu_order' => 0,
            'meta_description' => 'Het Vrolijk Toneel Initiatief (VTI) uit Kontich speelt sinds 1997 elk jaar toneel. Ontdek onze komende voorstelling en alle voorbije toneelstukken.',
            'blocks' => [
                ['type' => 'hero', 'data' => [
                    'title' => 'Het Vrolijk Toneel Initiatief',
                    'subtitle' => 'Sinds 1997 elk jaar op de planken in Kontich',
                    'intro' => null,
                    'image' => 'pages/story/hero.jpg',
                    'height' => 'large',
                    'overlay' => 'medium',
                ]],
                ['type' => 'productions', 'data' => ['heading' => 'Binnenkort', 'mode' => 'upcoming', 'limit' => null, 'show_more' => false, 'empty_text' => 'Ons volgende toneelstuk wordt binnenkort aangekondigd. Volg ons op Facebook!']],
                ['type' => 'productions', 'data' => ['heading' => 'Voorbije voorstellingen', 'mode' => 'past', 'limit' => 8, 'show_more' => true, 'empty_text' => null]],
                ['type' => 'divider', 'data' => ['style' => 'stars']],
                ['type' => 'text', 'data' => ['heading' => null, 'lead' => true, 'content' => '<p style="text-align:center">Hoe begint een toneelvereniging? Lees het hele verhaal: de geschiedenis, het heden, achter de schermen en Het Goede Doel Pieter Van de Poel.</p>']],
                ['type' => 'button', 'data' => ['label' => 'Lees ons verhaal', 'url' => '/story', 'new_tab' => false]],
            ],
        ]);
    }

    private function productions(): void
    {
        if (Production::query()->exists()) {
            return;
        }

        $charities = [
            2010 => 'Damiaanactie', 2011 => 'vzw Rako (dorp in Cambodja)', 2012 => 'vzw Kinderkanker Leuven', 2013 => 'Damiaanactie',
            2014 => 'Dagwerking Pegode Kontich', 2015 => 'vzw Bollé Bollé', 2016 => 'Kikker Kontich', 2017 => 'Schakel Kontich',
            2018 => 'De Kompanie en Open Kamp', 2019 => 'Tweelicht & Zoon',
        ];

        $plays = [
            1998 => ['Over Olifantjes', null], 1999 => ['Scherp Zand', null], 2000 => ['Lastige mensen & Co', null], 2001 => ['Hij was zo vrolijk', null],
            2002 => ['De Gijzeling', null], 2003 => ['Schipbreuk', null], 2004 => ['De Reconstructie', null], 2005 => ['Waarom Tobias zo gelukkig was', null],
            2006 => ['Een ongeluk komt nooit alleen', null], 2007 => ['De Reportagemakers', null], 2008 => ['De Overlever', null], 2009 => ['De Hut', null],
            2010 => ['GEWELDig', null],
            2011 => ['Code O.R.T.A', 'https://www.facebook.com/Code-ORTA-102483726493809/'],
            2012 => ['Minestrone & Pizza', 'https://www.facebook.com/Minestrone-Pizza-116638215114584/'],
            2013 => ['Storm!', 'https://www.facebook.com/Storm-468984439790605/'],
            2014 => ['De Laatste Ronde', 'https://www.facebook.com/delaatsteronde/'],
            2015 => ['De Verbouwing', 'https://www.facebook.com/De-Verbouwing-1592349204331621/'],
            2016 => ['De Wereld van Sem', 'https://www.facebook.com/De-Wereld-van-Sem-1679512732325850/'],
            2017 => ['Het Project', 'https://www.facebook.com/vrolijktoneelinitiatief/'],
            2018 => ['KillVille', 'https://www.facebook.com/Killville-125934331435720/'],
            2019 => ['Het niet geheel geheime dagboek van 6-13', 'https://www.facebook.com/Het-niet-geheel-geheime-dagboek-van-6-13-333262553947287/'],
            2021 => ['Verkens', 'https://www.facebook.com/Verkens-111841273682398/'],
        ];

        foreach ($plays as $year => [$title, $facebook]) {
            $venue = $year <= 2010 ? 'Refter van het Vrij Technisch Instituut, Kontich' : ($year >= 2021 ? 'Altenazaal, Kontich' : 'Zaal De Vleugel, Kontich');
            $text = '<p>'.e($title).' was het toneelstuk van '.$year.'. Vul deze pagina aan met de inhoud, de cast, foto\'s en video\'s.</p>';

            if ($facebook) {
                $text .= '<p><a href="'.e($facebook).'" target="_blank" rel="noopener">Bekijk de Facebookpagina van '.e($title).'</a></p>';
            }

            Production::query()->create([
                'title' => $title,
                'slug' => Str::slug($title).'-'.$year,
                'topic' => null,
                'starts_on' => $year.'-01-01',
                'ends_on' => $year.'-12-31',
                'date_label' => (string) $year,
                'venue' => $venue,
                'charity' => $charities[$year] ?? null,
                'is_published' => true,
                'blocks' => [['type' => 'text', 'data' => ['heading' => null, 'lead' => false, 'content' => $text]]],
            ]);
        }
    }

    private function shop(): void
    {
        if (! ShopCategory::query()->exists()) {
            $menu = [
                'Frisdrank' => [['Cola', 5], ['Cola Zero', 5], ['Plat water', 5], ['Bruiswater', 5], ['Ice Tea', 5], ['Fanta', 5], ['Fruitsap', 5]],
                'Bieren & wijn' => [['Maes', 5], ['Maes 0,0%', 5], ['Grimbergen Double', 9], ['Duvel', 9], ['Cava', 9], ['Cava 0%', 9], ['Witte wijn', 8], ['Rode wijn', 8]],
                'Snacks' => [['Hotdog', 5, 'Basis hotdog'], ['Hotdog halal', 5, 'Basis hotdog (halal)']],
                'Koffie & thee' => [['Koffie', 4], ['Thee', 4]],
            ];

            $categorySort = 0;
            foreach ($menu as $category => $products) {
                $cat = ShopCategory::query()->create(['name' => $category, 'sort' => ++$categorySort, 'is_active' => true]);

                foreach ($products as $sort => $product) {
                    Product::query()->create([
                        'shop_category_id' => $cat->id,
                        'name' => $product[0],
                        'price' => $product[1],
                        'description' => $product[2] ?? null,
                        'sort' => $sort + 1,
                    ]);
                }
            }
        }

        if (! ShopTable::query()->exists()) {
            foreach (range('A', 'F') as $row) {
                foreach (range(1, 5) as $number) {
                    ShopTable::query()->create(['row' => $row, 'number' => $number]);
                }
            }

            // The old site had a table at the technical tower, at the back of the hall.
            ShopTable::query()->create(['row' => 'T', 'number' => 1, 'name' => 'Technische toren']);
        }
    }
}
