<?php

namespace Database\Seeders;

use App\Models\Page;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * The story of the original vrolijktoneelinitiatief.be (Adobe Spark page by Walter Van Goethem) as editable blocks
 * on the home page. Photos are copied to the public disk so they can be replaced in the admin site.
 * Runs once: an existing home page is left alone unless --force is given through the "fresh" flag.
 */
class StorySeeder extends Seeder
{
    public function run(bool $fresh = false): void
    {
        $this->copyImages();

        if (! $fresh && Page::query()->where('slug', Page::HOME_SLUG)->exists()) {
            $this->command?->info('Startpagina bestaat al, niet overschreven.');

            return;
        }

        Page::query()->updateOrCreate(['slug' => Page::HOME_SLUG], [
            'title' => 'Het verhaal',
            'seo_title' => null,
            'meta_description' => 'Hoe begint een toneelvereniging? Het verhaal van Het Vrolijk Toneel Initiatief uit Kontich: de geschiedenis sinds 1997, het heden, achter de schermen en Het Goede Doel Pieter Van de Poel.',
            'is_published' => true,
            'show_in_menu' => false,
            'menu_order' => 0,
            'blocks' => $this->blocks(),
        ]);

        Page::query()->firstOrCreate(['slug' => 'privacy'], [
            'title' => 'Privacy',
            'is_published' => true,
            'show_in_menu' => false,
            'menu_order' => 90,
            'meta_description' => 'Hoe Het Vrolijk Toneel Initiatief omgaat met je gegevens.',
            'blocks' => [
                $this->block('text', [
                    'heading' => null,
                    'lead' => true,
                    'content' => '<p>Deze website gebruikt geen cookies om je te volgen. Pas deze tekst aan in het beheer (Website → Pagina\'s → Privacy).</p>',
                ]),
                $this->block('text', [
                    'heading' => 'Bezoekersstatistieken',
                    'content' => '<p>Om te weten hoeveel mensen onze website bezoeken, bewaren we per bezoek de bekeken pagina, het tijdstip, het IP-adres, het land en het type toestel en browser. Deze gegevens worden enkel gebruikt voor statistieken en om misbruik tegen te gaan, worden niet gedeeld met derden en worden na twee jaar automatisch verwijderd.</p>',
                ]),
                $this->block('text', [
                    'heading' => 'Video\'s',
                    'content' => '<p>Video\'s van YouTube worden pas geladen wanneer je op de afspeelknop klikt. Vanaf dan gelden de voorwaarden van YouTube (Google).</p>',
                ]),
            ],
        ]);
    }

    private function copyImages(): void
    {
        $source = public_path('images/story');

        if (! is_dir($source)) {
            return;
        }

        foreach (File::files($source) as $file) {
            $target = 'pages/story/'.$file->getFilename();

            if (! Storage::disk('public')->exists($target)) {
                Storage::disk('public')->put($target, File::get($file->getPathname()));
            }
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{type: string, data: array<string, mixed>}
     */
    private function block(string $type, array $data): array
    {
        return ['type' => $type, 'data' => $data];
    }

    private function img(string $name): string
    {
        return 'pages/story/'.$name;
    }

    /**
     * @param  list<string>  $paragraphs
     */
    private function html(array $paragraphs): string
    {
        return collect($paragraphs)->map(fn (string $p): string => '<p>'.$p.'</p>')->implode('');
    }

    private function link(string $url, string $text): string
    {
        return '<a href="'.e($url).'" target="_blank" rel="noopener">'.e($text).'</a>';
    }

    /**
     * @return list<array{type: string, data: array<string, mixed>}>
     */
    private function blocks(): array
    {
        $fb = 'https://www.facebook.com/Vrolijk-Toneel-Initiatief-1405183079697953/';

        return [
            $this->block('hero', [
                'title' => 'Het Vrolijk Toneel Initiatief',
                'subtitle' => 'De geschiedenis, het heden, de toekomst en achter de schermen',
                'intro' => 'Hoe begint nu eigenlijk een toneelvereniging? Wat zijn de obstakels doorheen de jaren die je moet overbruggen? En hoe blijft een toneelvereniging bestaan? Welkom bij het verhaal over Het Vrolijk Toneel Initiatief!',
                'image' => $this->img('hero.jpg'),
                'height' => 'full',
                'overlay' => 'medium',
            ]),

            // Geschiedenis
            $this->block('chapter', ['title' => 'Geschiedenis', 'anchor' => 'geschiedenis', 'subtitle' => 'Sinds 1997', 'image' => null, 'in_nav' => true]),
            $this->block('text', ['heading' => null, 'lead' => false, 'content' => $this->html([
                'Het '.$this->link($fb, 'Vrolijk Toneel Initiatief').' of kort gezegd "VTI" heeft al een hele reeks toneelstukken achter zich liggen. Om beter te begrijpen hoe dit allemaal ontstaan is, gaan we terug naar 1997.',
            ])]),
            $this->block('text', ['heading' => '1997', 'lead' => false, 'content' => $this->html([
                'Het Vrolijk Toneel Initiatief ontstond vanuit een vergadering van de toenmalige leerlingenraad waar Walter Van Goethem (regisseur en hoofd technische ploeg), als nieuwe leerkracht op het Vrij Technisch Instituut te Kontich, medebegeleider was. De leerlingenraad werd toen voorgezeten door Dirk Guinée en samen met Wendy Baekelmans, Wouter Vandervliet, Veerle Andriessen, Jimmy Wenderickx en leerkracht Ann De Clercq werd de basis gelegd voor een mooie traditie.',
                'De eerste jaren werd er in de refter van de school gespeeld. Die werd dan telkens omgebouwd tot een theaterzaal. Vanaf 2005 werd er ook telkens een soundtrack voor het toneel geschreven. De eerste drie werden volledig live gespeeld door leerlingen en leerkrachten. Vanaf 2008 werd de muziek opgenomen op cd en bij de voorstellingen afgespeeld. Er werden dan telkens fragmenten gebruikt.',
            ])]),
            $this->block('timeline', [
                'heading' => 'Voorbije toneelstukken van 1997 tot 2010',
                'items' => collect([
                    1998 => 'Over Olifantjes', 1999 => 'Scherp Zand', 2000 => 'Lastige mensen & Co', 2001 => 'Hij was zo vrolijk',
                    2002 => 'De Gijzeling', 2003 => 'Schipbreuk', 2004 => 'De Reconstructie', 2005 => 'Waarom Tobias zo gelukkig was',
                    2006 => 'Een ongeluk komt nooit alleen', 2007 => 'De Reportagemakers', 2008 => 'De Overlever', 2009 => 'De Hut', 2010 => 'GEWELDig',
                ])->map(fn (string $title, int $year): array => ['year' => (string) $year, 'title' => $title, 'url' => null, 'note' => null])->values()->all(),
            ]),
            $this->block('text', ['heading' => null, 'lead' => false, 'content' => $this->html([
                'Vanaf 2010 werd besloten om uit te wijken naar een andere zaal, waar de toneelstukken beter tot hun recht zouden komen. Beter geluid, betere belichting, een groter podium, een betere backstage voor de acteurs en geen organisatorische problemen met de middagrefter waren enkele van de redenen om vanaf dat jaar in zaal De Vleugel onderdak te zoeken.',
            ])]),
            $this->block('timeline', [
                'heading' => 'Voorbije toneelstukken sinds 2010',
                'items' => [
                    ['year' => '2011', 'title' => 'Code O.R.T.A', 'url' => 'https://www.facebook.com/Code-ORTA-102483726493809/', 'note' => null],
                    ['year' => '2012', 'title' => 'Minestrone & Pizza', 'url' => 'https://www.facebook.com/Minestrone-Pizza-116638215114584/', 'note' => null],
                    ['year' => '2013', 'title' => 'Storm!', 'url' => 'https://www.facebook.com/Storm-468984439790605/', 'note' => null],
                    ['year' => '2014', 'title' => 'De Laatste Ronde', 'url' => 'https://www.facebook.com/delaatsteronde/', 'note' => null],
                    ['year' => '2015', 'title' => 'De Verbouwing', 'url' => 'https://www.facebook.com/De-Verbouwing-1592349204331621/', 'note' => null],
                    ['year' => '2016', 'title' => 'De Wereld van Sem', 'url' => 'https://www.facebook.com/De-Wereld-van-Sem-1679512732325850/', 'note' => null],
                    ['year' => '2017', 'title' => 'Het Project', 'url' => 'https://www.facebook.com/vrolijktoneelinitiatief/', 'note' => null],
                    ['year' => '2018', 'title' => 'KillVille', 'url' => 'https://www.facebook.com/Killville-125934331435720/', 'note' => null],
                    ['year' => '2019', 'title' => 'Het niet geheel geheime dagboek van 6-13', 'url' => 'https://www.facebook.com/Het-niet-geheel-geheime-dagboek-van-6-13-333262553947287/', 'note' => null],
                    ['year' => '2021', 'title' => 'Verkens', 'url' => 'https://www.facebook.com/Verkens-111841273682398/', 'note' => null],
                ],
            ]),
            $this->block('gallery', [
                'images' => array_map(fn (int $i): string => $this->img("groep-{$i}.jpg"), range(1, 10)),
                'caption' => 'Groepsfoto\'s van de afgelopen jaren - foto\'s door Walter Van Goethem',
                'layout' => 'masonry',
                'columns' => '3',
            ]),
            $this->block('text', ['heading' => '2019: Het niet geheel geheime dagboek van 6-13', 'lead' => false, 'content' => $this->html([
                'Sinds 2019 zijn er serieuze veranderingen gebeurd bij de toneelvereniging. Eén van die veranderingen was de tribune: de technische ploeg heeft in overleg met de regisseur besloten om sindsdien de tribune te gebruiken van een andere toneelvereniging ('.$this->link('https://www.facebook.com/TejatergroepBlackOut/', 'Black Out').') die ook al enkele jaren in dezelfde zaal speelt.',
                'Elk jaar opnieuw is er na de laatste voorstelling een plezante afterparty. Hier een kleine trailer van hoe zo\'n afterparty eruitziet:',
            ])]),
            $this->block('video', ['url' => 'https://www.youtube.com/watch?v=b4GpJ4FHz3g', 'caption' => 'De afterparty']),
            $this->block('text', ['heading' => null, 'lead' => false, 'content' => $this->html([
                'Na de eerste afterparty was er ook een plezante tweede afterparty waar het jaar werd afgesloten, waarbij de regisseur, die ook de coördinator van de technische ploeg is, laat zien hoe je de tapijten van de gebruikte tribune moet stofzuigen.',
            ])]),
            $this->block('video', ['url' => 'https://www.youtube.com/watch?v=4C199rTW1es', 'caption' => 'De tweede afterparty']),

            // Heden
            $this->block('chapter', ['title' => 'Heden', 'anchor' => 'heden', 'subtitle' => null, 'image' => null, 'in_nav' => true]),
            $this->block('text', ['heading' => null, 'lead' => false, 'content' => $this->html([
                'Het VTI staat dit jaar voor de 23ste keer op de planken in de Altenazaal te Kontich. Oud-leerlingen en leerkrachten zijn hiervoor teruggekomen bij de acteurs om er een onvergetelijk jaar van te maken. De toneelvereniging pakt dit jaar uit met het stuk "'.$this->link('https://www.facebook.com/Verkens-111841273682398/', 'Verkens').'". Uitspraken die al jaar en dag meegaan in de toneelgroep, die uitgegroeid is tot een hechte vriendengroep (eigenlijk meer een familie), zijn verwerkt in het toneelstuk, waaronder één van de meest gebruikte uitspraken tijdens de audities:',
            ])]),
            $this->block('quote', ['text' => 'Vroeger hé, dan moeste wij werke voor ne patat!', 'author' => 'Jeroen Van Der Stam']),
            $this->block('video', ['url' => 'https://www.youtube.com/watch?v=XxNpwpEcDXw', 'caption' => null]),
            $this->block('text', ['heading' => null, 'lead' => false, 'content' => $this->html([
                'Een repetitie kan al eens serieus genomen worden, maar af en toe is er toch een beetje tijd om een liedje te zingen dat de repetitie even stillegt.',
            ])]),
            $this->block('video', ['url' => 'https://www.youtube.com/watch?v=u3xZvVzbjgw', 'caption' => null]),
            $this->block('text', ['heading' => null, 'lead' => false, 'content' => $this->html([
                'Bij elke repetitie wordt er al eens gelachen, toch moesten de acteurs moeite doen om hun lach in te houden bij de eerste lezing van het script van Verkens.',
            ])]),
            $this->block('video', ['url' => 'https://www.youtube.com/watch?v=dc2NcJHft0s', 'caption' => 'De eerste lezing van Verkens']),

            // Achter de schermen
            $this->block('chapter', ['title' => 'Achter de schermen', 'anchor' => 'achter-de-schermen', 'nav_title' => 'Achter de schermen', 'subtitle' => 'De technische ploeg', 'image' => null, 'in_nav' => true]),
            $this->block('text', ['heading' => null, 'lead' => false, 'content' => $this->html([
                'Natuurlijk bestaat een toneelgroep niet alleen uit acteurs, maar ook uit een technische ploeg of kortweg "de TP". De TP is mee ontstaan in 1997, ook eerst klein begonnen en al snel uitgegroeid tot een grote groep mensen, waaronder veel oud-leerlingen, huidige leerlingen van het VTI en andere scholen. Maar ook leerkrachten die tijdens de paasvakantie meehelpen met het realiseren van decors, special effects, licht, geluid, video en zoveel meer.',
                'In 2013 realiseerde de technische ploeg in samenwerking met de afdeling lassen een draaiend podium voor de productie van "Storm!". Sindsdien wordt er goed op los gedraaid, maar in 2015 sloeg het noodlot toe. De technische ploeg kwam tot de vaststelling dat het draaiend podium elke ronde een paar centimeter naar achter opschoof. Dus gingen ze aan de slag:',
            ])]),
            $this->block('video', ['url' => 'https://www.youtube.com/watch?v=54BUwKn8PMY', 'caption' => 'Het draaiend podium']),
            $this->block('text', ['heading' => null, 'lead' => false, 'content' => $this->html([
                'In 2017 was er een groter draaiend podium nodig. Iemand die lid was van de TP en afstudeerde in de richting lassen, ging aan de slag met een nieuw draaiend podium, dat uiteindelijk ook zijn eindwerk werd.',
            ])]),
            $this->block('video', ['url' => 'https://www.youtube.com/watch?v=11UY2RTNnu4', 'caption' => 'Het nieuwe draaiend podium (2017)']),
            $this->block('list', [
                'heading' => 'Gerealiseerde projecten door de TP sinds 2013',
                'intro' => 'In de voorbije jaren heeft de TP serieus wat projecten gerealiseerd, waardoor de toneelstukken hun eigen technische twist kregen. In volgorde:',
                'items' => [
                    'Valschermen (2013)', 'Eerste draaiend podium (2013)', 'Boxring (2014)', 'Kampvuur (2015)', 'Stemvervormers (2016)',
                    'Geautomatiseerde dubbele deur (2016)', 'Vooruitspringende heksenpop op luchtdruk (2016)', 'Zwevende stoel (2016)',
                    'Nieuw draaiend podium (2017)', 'Robotarm (2017)', 'Denkballon (2017)', 'Grote rijdende en vliegende insecten (2018)',
                    'Nieuwsstudio met hulp van een green screen (2018)', 'Sniffers: apparaten die projectiedoeken "opzuigen" (2018)',
                    '3 plooitafels: tafel wordt rek (2019)', 'Railsysteem voor projectiedoeken (2019)', 'Cel met blacklight (2019)',
                ],
                'style' => 'columns',
            ]),

            // Het Goede Doel
            $this->block('chapter', ['title' => 'Het Goede Doel Pieter Van de Poel', 'anchor' => 'goede-doel', 'nav_title' => 'Goede doel', 'subtitle' => 'Doe goed.', 'image' => null, 'in_nav' => true]),
            $this->block('text', ['heading' => null, 'lead' => false, 'content' => $this->html([
                '22 jaar geleden werd er voor het eerst toneel gespeeld in het Vrij Technisch Instituut van Kontich. Een groepje zesdejaars wou iets speciaals doen en het idee van een toneelstuk werd eerst door heel wat mensen weggelachen, maar kreeg gaandeweg meer en meer vorm. Toen de drie voorstellingen achter de rug waren, sloeg de stemming om.',
                'Het Vrolijk Toneel Initiatief was geboren en zou aan een langzame weg beginnen timmeren. Veel leerlingen kwamen, sommigen haakten snel af, maar de meesten voelden de warmte die onze groep jaar na jaar uitstraalde. Onze toneelvereniging werd een groep vrienden waar door dik en dun gewerkt werd aan één theaterproject per jaar. Niet alleen leerlingen die op de planken wilden staan vonden de weg naar ons clubje, ook techniekers die wilden experimenteren met muziek, geluid, belichting, decor en alles wat bij een toneelstuk hoort. We verwelkomden niet enkel de haantjes-de-voorsten: ook leerlingen die het soms iets moeilijker hadden (en hebben) kregen bij ons de kans om nieuwe horizonten te ontdekken. We kunnen niet opsommen welke kleine bloemetjes allemaal volledig openbloeiden, de lijst zou te lang zijn.',
                'Eén van die kleine bloemetjes was Pieter Van de Poel. Hij was een jongen die het heel moeilijk had om zich uit te drukken. Geremd door een spraakgebrek kwam hij toch auditie doen en hij kreeg één van de kleinste rolletjes die er ooit geschreven waren voor het Vrolijk Toneel Initiatief. Hij gooide zich vol overgave en overtuiging op zijn opdracht en genoot er met volle teugen van. De jaren die volgden combineerde hij zijn passie voor de scouts (hij zat in de fantastische scouts 115 in Mortsel) met zijn passie voor toneel, en elk jaar kreeg hij een iets groter rolletje.',
                'Tot die ene dag, toen hij totaal onverwacht afscheid nam van ons.',
                'Pieter liet ons achter met een groot gemis. Hij wist waarschijnlijk niet hoeveel mensen om hem gaven; bij het afscheid zat de kerk stampvol. Allemaal mensen die van hem hielden, alleen … hij wist dat niet.',
                'Pieter zit nog steeds in ons hart en we willen dat hij daar nog heel lang blijft zitten. Daarom hebben we in datzelfde jaar, 2010, besloten om elk jaar ons hart open te stellen voor mensen die het nodig hebben. Mensen die via ons mogen genieten van de schoonheid die Pieter in zich droeg.',
            ])]),
            $this->block('image', ['image' => $this->img('pieter-van-de-poel.jpg'), 'caption' => 'Laatste groepsfoto met Pieter Van de Poel - foto van Walter Van Goethem', 'width' => 'wide']),
            $this->block('text', ['heading' => null, 'lead' => false, 'content' => $this->html([
                'Zo werd "Het Goede Doel Pieter Van de Poel" geboren: een actie waarbij we een voorstelling spelen waarvan de opbrengst elk jaar naar een goed doel gaat.',
            ])]),
            $this->block('timeline', [
                'heading' => 'De goede doelen',
                'items' => [
                    ['year' => '2010', 'title' => 'Damiaanactie', 'url' => null, 'note' => null],
                    ['year' => '2011', 'title' => 'vzw Rako', 'url' => null, 'note' => 'ten voordele van een dorp in Cambodja'],
                    ['year' => '2012', 'title' => 'vzw Kinderkanker Leuven', 'url' => null, 'note' => null],
                    ['year' => '2013', 'title' => 'Damiaanactie', 'url' => null, 'note' => null],
                    ['year' => '2014', 'title' => 'Dagwerking Pegode Kontich', 'url' => null, 'note' => null],
                    ['year' => '2015', 'title' => 'vzw Bollé Bollé', 'url' => null, 'note' => null],
                    ['year' => '2016', 'title' => 'Kikker Kontich', 'url' => null, 'note' => null],
                    ['year' => '2017', 'title' => 'Schakel Kontich', 'url' => null, 'note' => null],
                    ['year' => '2018', 'title' => 'De Kompanie en Open Kamp', 'url' => null, 'note' => null],
                    ['year' => '2019', 'title' => 'Tweelicht & Zoon', 'url' => null, 'note' => null],
                ],
            ]),
            $this->block('text', ['heading' => null, 'lead' => false, 'content' => $this->html([
                'We weten dat Pieter via zijn familie erg betrokken was bij de Damiaanactie en er elk jaar hard zijn best voor deed. Daarmee gaf hij ons een niet mis te verstane boodschap: "Doe goed."',
            ])]),

            // Toekomst
            $this->block('chapter', ['title' => 'Toekomst', 'anchor' => 'toekomst', 'subtitle' => null, 'image' => null, 'in_nav' => true]),
            $this->block('text', ['heading' => null, 'lead' => true, 'content' => $this->html([
                'Wat de toekomst ons nog brengt, zijn hopelijk nog een paar toneelstukken om voor te bereiden, uit te werken en voor te brengen aan het publiek. Een plezante groep mensen om mee samen te werken. Uitdagende projecten voor de technische ploeg.',
                'Ik hoop dat u er evenveel van genoten heeft om dit te lezen als ik toen ik het schreef.',
            ])]),
            $this->block('divider', ['style' => 'stars']),
            $this->block('text', ['heading' => null, 'lead' => false, 'content' => '<p style="text-align:center"><em>Verhaal: Walter Van Goethem</em></p>']),
        ];
    }
}
