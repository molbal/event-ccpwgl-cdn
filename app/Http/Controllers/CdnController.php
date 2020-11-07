<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ZipArchive;

class CdnController extends Controller
{
    public function serve(string $file) {
        $file = '/eve-cache/'.$file;
        // 1: File exists
        if ($this->fileExists($file)) {
            return $this->serveFile($file);
        }

        // 2: CDN redirect
        $newFile = $this->getCdnRecord($file);
        if (!$newFile) {
            return ['error' => "The given file $file does not exist in the filesystem and there is no CDN redirect record pointing towards it."];
        }

        // CDN exists to the new file
        if ($this->fileExists($newFile)) {
            return $this->serveFile($newFile);
        }
        return ['error' => "The given file $file does exists in the CDN records, but does not exist in the filesystem."];

    }

    private function serveFile($file) {

        if ($this->isCake($file)) {
            return $this->serveCakeFile($file);
        }
        else {
            return $this->serveNonCakeFile($file);
        }

    }

    private function serveNonCakeFile($file) {
        return redirect(config('app.url').'/storage'.$file);
    }

    private function serveCakeFile($file) {
        if (!$this->hasUnzippedCake($file)) {
            $this->unzipCake($file);
        }
        return $this->serveNonCakeFile($this->getUnzippedFilename($file));
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

    private function isCake($file) {
        return (Str::of($file)->endsWith(".cake"));
    }


    private function getUnzippedFilename($file) {
        return Str::replaceFirst('.cake','.unzipped.cake', $file);
    }
    private function hasUnzippedCake($file) {
        return $this->fileExists($this->getUnzippedFilename($file));
    }

    private function unzipCake($file) {
        Log::info("Unzipping $file");

        $zipPath = storage_path('app/public') . $file;
        $unzipPath = $this->getUnzippedFilename($zipPath);
        $gz = gzopen($zipPath, 'rb');
        if ($gz) {

            $out_file = fopen($unzipPath, 'c+');
            while (!gzeof($gz)) {
                // Read buffer-size bytes
                // Both fwrite and gzread and binary-safe
                fwrite($out_file, gzread($gz, 4096));
            }

            fclose($out_file);
            gzclose($gz);
        } else {
            throw new \RuntimeException('Could not open cake at ' . $zipPath . "($gz)");
        }
    }

}
