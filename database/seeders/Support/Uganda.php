<?php

namespace Database\Seeders\Support;

/**
 * Realistic Ugandan demo data: names from across the regions (Buganda, Busoga, Ankole/Kigezi,
 * Acholi/Lango, Teso, Bugisu, West Nile), MTN/Airtel mobile numbers, NIN-style IDs and
 * Kampala-area addresses. All people generated here are fictional.
 */
class Uganda
{
    public const MALE_FIRST = [
        'John', 'Joseph', 'Peter', 'Paul', 'Moses', 'David', 'Samuel', 'Emmanuel', 'Isaac', 'Ivan',
        'Brian', 'Ronald', 'Denis', 'Robert', 'Godfrey', 'Patrick', 'Charles', 'Andrew', 'Henry', 'Simon',
        'Edward', 'Richard', 'Joshua', 'Daniel', 'Geoffrey', 'Fred', 'Julius', 'Hassan', 'Ali', 'Musa',
        'Collins', 'Allan', 'Arthur', 'Timothy', 'Martin', 'Herbert', 'Benon', 'Gerald', 'Innocent', 'Hillary',
    ];

    public const FEMALE_FIRST = [
        'Sarah', 'Grace', 'Esther', 'Ruth', 'Mary', 'Agnes', 'Florence', 'Harriet', 'Juliet', 'Annet',
        'Patience', 'Prossy', 'Brenda', 'Sharon', 'Doreen', 'Joan', 'Winnie', 'Rebecca', 'Phiona', 'Irene',
        'Josephine', 'Margaret', 'Betty', 'Rose', 'Lydia', 'Mercy', 'Gloria', 'Allen', 'Rita', 'Scovia',
        'Christine', 'Catherine', 'Immaculate', 'Hadijah', 'Aisha', 'Faith', 'Peace', 'Brenda', 'Edith', 'Damalie',
    ];

    /** Surnames used by men (many Luganda names are gendered). */
    public const MALE_SURNAMES = [
        'Kato', 'Mukasa', 'Ssemakula', 'Kizza', 'Ssali', 'Lubega', 'Musoke', 'Kiggundu', 'Ssebunya', 'Wasswa',
        'Kasozi', 'Mayanja', 'Ssekandi', 'Kawuki', 'Ssenyonga', 'Waiswa', 'Mugisha', 'Tumusiime', 'Byaruhanga',
        'Twinomugisha', 'Mwesigwa', 'Tumwine', 'Ahimbisibwe', 'Okello', 'Ojok', 'Opio', 'Odongo', 'Ocen', 'Okot',
        'Otim', 'Ekwaro', 'Okiror', 'Wanyama', 'Wafula', 'Masaba', 'Wandera', 'Drani', 'Ayume', 'Bamwine', 'Kagimu',
    ];

    public const FEMALE_SURNAMES = [
        'Nakato', 'Nambi', 'Namukasa', 'Nalubega', 'Nantongo', 'Namatovu', 'Nakabugo', 'Nalwoga', 'Namutebi',
        'Babirye', 'Nabirye', 'Nakimuli', 'Nansubuga', 'Namubiru', 'Nassuuna', 'Kyomuhendo', 'Ainembabazi', 'Kansiime',
        'Ninsiima', 'Kemigisha', 'Asiimwe', 'Atuhaire', 'Achieng', 'Akello', 'Atim', 'Auma', 'Apio', 'Lamwaka',
        'Adong', 'Acen', 'Amongin', 'Nafula', 'Nekesa', 'Namono', 'Driciru', 'Ayikoru', 'Kirabo', 'Nakiwala', 'Nankya', 'Kobusingye',
    ];

    /** [area, road] pairs around Kampala and neighbouring towns. */
    public const AREAS = [
        ['Ntinda', 'Ntinda Road'], ['Kololo', 'Acacia Avenue'], ['Bukoto', 'Bukoto Street'], ['Nakawa', 'Jinja Road'],
        ['Kawempe', 'Bombo Road'], ['Rubaga', 'Rubaga Road'], ['Makindye', 'Makindye Road'], ['Kansanga', 'Ggaba Road'],
        ['Muyenga', 'Tank Hill Road'], ['Kabalagala', 'Ggaba Road'], ['Wandegeya', 'Bombo Road'], ['Mengo', 'Kabaka Anjagala Road'],
        ['Nsambya', 'Nsambya Road'], ['Najjera', 'Kira Road'], ['Kira', 'Kira-Kasangati Road'], ['Kireka', 'Jinja Road'],
        ['Bweyogerere', 'Jinja Road'], ['Namugongo', 'Namugongo Road'], ['Mukono', 'Jinja Road'], ['Entebbe', 'Entebbe Road'],
        ['Kajjansi', 'Entebbe Road'], ['Nansana', 'Hoima Road'], ['Gayaza', 'Gayaza Road'], ['Lubowa', 'Entebbe Road'],
        ['Mutundwe', 'Masaka Road'], ['Kyengera', 'Masaka Road'], ['Naalya', 'Northern Bypass'], ['Kisaasi', 'Kisaasi Road'],
    ];

    public const TOWNS = [
        'Ntinda' => 'Kampala', 'Kololo' => 'Kampala', 'Bukoto' => 'Kampala', 'Nakawa' => 'Kampala', 'Kawempe' => 'Kampala',
        'Rubaga' => 'Kampala', 'Makindye' => 'Kampala', 'Kansanga' => 'Kampala', 'Muyenga' => 'Kampala', 'Kabalagala' => 'Kampala',
        'Wandegeya' => 'Kampala', 'Mengo' => 'Kampala', 'Nsambya' => 'Kampala', 'Najjera' => 'Wakiso', 'Kira' => 'Wakiso',
        'Kireka' => 'Wakiso', 'Bweyogerere' => 'Wakiso', 'Namugongo' => 'Wakiso', 'Mukono' => 'Mukono', 'Entebbe' => 'Wakiso',
        'Kajjansi' => 'Wakiso', 'Nansana' => 'Wakiso', 'Gayaza' => 'Wakiso', 'Lubowa' => 'Wakiso', 'Mutundwe' => 'Kampala',
        'Kyengera' => 'Wakiso', 'Naalya' => 'Wakiso', 'Kisaasi' => 'Kampala',
    ];

    /** MTN (77x, 78x, 76x) and Airtel (70x, 75x, 74x) prefixes. */
    private const MOBILE_PREFIXES = ['772', '774', '775', '776', '777', '778', '782', '783', '785', '787', '789', '761', '701', '702', '703', '704', '705', '706', '750', '752', '753', '755', '756', '757', '740', '744'];

    public static function name(string $gender): string
    {
        return $gender === 'female'
            ? self::pick(self::FEMALE_FIRST).' '.self::pick(self::FEMALE_SURNAMES)
            : self::pick(self::MALE_FIRST).' '.self::pick(self::MALE_SURNAMES);
    }

    /** e.g. "+256 772 418 093" */
    public static function phone(): string
    {
        return sprintf('+256 %s %03d %03d', self::pick(self::MOBILE_PREFIXES), random_int(0, 999), random_int(0, 999));
    }

    /** NIN-style national ID: C + M/F + 2-digit birth year + 10 alphanumerics = 14 chars. */
    public static function nin(string $gender, string $dateOfBirth): string
    {
        $chars = '0123456789ABCDEFGHJKLMNPQRSTUVWXYZ';
        $tail = '';
        for ($i = 0; $i < 10; $i++) {
            $tail .= $chars[random_int(0, strlen($chars) - 1)];
        }

        return 'C'.($gender === 'female' ? 'F' : 'M').substr($dateOfBirth, 2, 2).$tail;
    }

    /** e.g. "Plot 37, Ntinda Road, Ntinda, Kampala" */
    public static function address(): string
    {
        [$area, $road] = self::pick(self::AREAS);

        return 'Plot '.random_int(1, 240).', '.$road.', '.$area.', '.self::TOWNS[$area];
    }

    /** firstname.lastname + digits @ a reserved example domain (never a real mailbox). */
    public static function email(string $name): string
    {
        $slug = strtolower(preg_replace('/[^a-z]+/i', '.', trim($name)));

        return trim($slug, '.').random_int(10, 999).'@example.com';
    }

    private static function pick(array $items)
    {
        return $items[array_rand($items)];
    }
}
