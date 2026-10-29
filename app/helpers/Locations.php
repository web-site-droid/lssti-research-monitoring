<?php

declare(strict_types=1);

namespace App\Helpers;

final class Locations
{
    /** @return array<string, list<string>> */
    public static function places(): array
    {
        return [
            'Lanao del Norte' => [
                'Bacolod', 'Baloi', 'Baroy', 'Kapatagan', 'Kauswagan', 'Kolambugan', 'Lala',
                'Linamon', 'Magsaysay', 'Maigo', 'Matungao', 'Munai', 'Nunungan', 'Pantao Ragat',
                'Pantar', 'Poona Piagapo', 'Salvador', 'Sapad', 'Sultan Naga Dimaporo', 'Tagoloan',
                'Tangcal', 'Tubod', 'Iligan City',
            ],
            'Lanao del Sur' => [
                'Bacolod-Kalawi', 'Balabagan', 'Balindong', 'Bayang', 'Binidayan', 'Buadiposo-Buntong',
                'Bubong', 'Butig', 'Calanogas', 'Ditsaan-Ramain', 'Ganassi', 'Kapai', 'Kapatagan',
                'Lumba-Bayabao', 'Lumbatan', 'Lumbayanague', 'Madalum', 'Madamba', 'Maguing', 'Malabang',
                'Marantao', 'Marawi City', 'Marogong', 'Masiu', 'Mulondo', 'Piagapo', 'Poona Bayabao',
                'Pualas', 'Saguiaran', 'Sultan Dumalondong', 'Picong', 'Tagoloan II', 'Tamparan',
                'Taraka', 'Tubaran', 'Tugaya', 'Wao',
            ],
            'Misamis Oriental' => [
                'Alubijid', 'Balingasag', 'Balingoan', 'Binuangan', 'Cagayan de Oro City', 'Claveria',
                'El Salvador City', 'Gingoog City', 'Gitagum', 'Initao', 'Jasaan', 'Kinoguitan',
                'Lagonglong', 'Laguindingan', 'Libertad', 'Lugait', 'Magsaysay', 'Manticao', 'Medina',
                'Naawan', 'Opol', 'Salay', 'Sugbongcogon', 'Tagoloan', 'Talisayan', 'Villanueva',
            ],
            'Misamis Occidental' => [
                'Aloran', 'Baliangao', 'Bonifacio', 'Calamba', 'Clarin', 'Concepcion', 'Don Victoriano Chiongbian',
                'Jimenez', 'Lopez Jaena', 'Oroquieta City', 'Ozamiz City', 'Panaon', 'Plaridel',
                'Sapang Dalaga', 'Sinacaban', 'Tangub City', 'Tudela',
            ],
            'Bukidnon' => [
                'Baungon', 'Cabanglasan', 'Damulog', 'Dangcagan', 'Don Carlos', 'Impasug-ong', 'Kadingilan',
                'Kalilangan', 'Kibawe', 'Kitaotao', 'Lantapan', 'Libona', 'Malaybalay City', 'Malitbog',
                'Manolo Fortich', 'Maramag', 'Pangantucan', 'Quezon', 'San Fernando', 'Sumilao', 'Talakag',
                'Valencia City',
            ],
        ];
    }

    /** @return array<string, array<string, list<string>>> */
    public static function barangays(): array
    {
        static $data = null;
        if ($data === null) {
            $raw = @file_get_contents(__DIR__ . '/barangays.json');
            $decoded = is_string($raw) ? json_decode($raw, true) : null;
            $data = is_array($decoded) ? $decoded : [];
        }
        return $data;
    }

    /** @return list<array{city: string, province: string}> */
    public static function options(): array
    {
        $options = [];
        foreach (self::places() as $province => $cities) {
            foreach ($cities as $city) {
                $options[] = ['city' => $city, 'province' => $province];
            }
        }
        return $options;
    }
}
