<?php

namespace App\Exports;

use App\Models\Cosplayer;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class CosplayersExport implements FromCollection, WithHeadings, WithMapping
{
    public function collection()
    {
        return Cosplayer::with('user')->get();
    }

    public function headings(): array
    {
        return ['ID', 'Name', 'Email', 'Character', 'Series', 'Experience', 'Registered At'];
    }

    public function map($cosplayer): array
    {
        return [
            $cosplayer->id,
            $cosplayer->user->name,
            $cosplayer->user->email,
            $cosplayer->character_name,
            $cosplayer->series,
            $cosplayer->experience_level,
            $cosplayer->created_at->format('Y-m-d H:i'),
        ];
    }
}
