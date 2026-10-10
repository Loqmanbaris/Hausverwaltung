<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Datei der Webseite: Logo, Startfoto, Objektfoto oder Download (PDF). */
class SiteAsset extends Model
{
    protected $fillable = ['kind', 'ref_id', 'title', 'file', 'mime', 'width', 'height', 'sort'];

    public function url(string $size = 'l'): string
    {
        $ext = $this->mime === 'application/pdf' ? 'pdf' : ($this->mime === 'image/png' ? 'png' : 'jpg');

        return route('site.asset', ['asset' => $this->id, 'size' => $this->kind === 'download' || $this->kind === 'logo' ? 'o' : $size, 'ext' => $ext]).'?v='.substr(md5($this->file), 0, 8);
    }
}
