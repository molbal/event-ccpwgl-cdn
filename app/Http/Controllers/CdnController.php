<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class CdnController extends Controller
{
    public function serve(string $file) {
        $file = '/eve-cache/'.$file;

        // 1: File exists
        if ($this->fileExists($file)) {
            return redirect(config('app.url').'/storage'.$file);
        }

        // 2: CDN redirect
        $newFile = $this->getCdnRecord($file);
        if (!$newFile) {
            return ['error' => "The given file $file does not exist in the filesystem and there is no CDN redirect record pointing towards it."];
        }

        // CDN exists to the new file
        if ($this->fileExists($newFile)) {
            return redirect(config('app.url').'/storage'.$newFile);
        }
        return ['error' => "The given file $file does exists in the CDN records, but does not exist in the filesystem."];

    }

    private function fileExists($file) {
        return Storage::disk('public')->exists($file);
    }

    private function getCdnRecord($file) {
        return Cache::remember('cdn.exists-'.md5($file), now()->addMinutes(15), function () use ($file){
            if ( DB::table('cdn_lookup')->where('cdn_url', $file)->exists()) {
                return DB::table('cdn_lookup')->where('cdn_url', $file)->value('local_path');
            }
            else {
                return null;
            }
        });
    }

}
