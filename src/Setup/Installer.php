<?php

namespace Ideal\Setup;

class Installer
{

    public function run(string $vendorDir): void
    {
        $rootDir = stream_resolve_include_path($vendorDir . '/..');

        $wwwDir = $rootDir . '/www';

        $this->copyFolder(
            $vendorDir . '/idealcms/bootstrap-multiselect/dist',
            $wwwDir . '/js/admin/bootstrap-multiselect'
        );

        $this->copyFolder(
            $vendorDir . '/idealcms/ckeditor/dist',
            $wwwDir . '/js/admin/ckeditor'
        );

        $this->copyFolder(
            $vendorDir . '/idealcms/ckfinder/dist',
            $wwwDir . '/js/admin/ckfinder'
        );

        $this->copyFolder(
            $vendorDir . '/components/jquery',
            $wwwDir . '/js/admin/jquery'
        );

        $this->copyFolder(
            $vendorDir . '/components/jqueryui',
            $wwwDir . '/js/admin/jqueryui'
        );

        $this->copyFolder(
            $vendorDir . '/twitter/bootstrap/dist/',
            $wwwDir . '/js/admin/bootstrap'
        );
    }

    protected function copyFolder(string $from, string $to): void
    {
        $dir = opendir($from);
        if (!file_exists($to) && !mkdir($to, 0777, true) && !is_dir($to)) {
            throw new \RuntimeException(sprintf('Directory "%s" was not created', $to));
        }
        while (($file = readdir($dir)) !== false) {
            if (($file !== '.') && ($file !== '..')) {
                if (is_dir($from . '/' . $file)) {
                    $this->copyFolder($from . '/' . $file, $to . '/' . $file);
                } else {
                    copy($from . '/' . $file, $to . '/' . $file);
                }
            }
        }
        closedir($dir);
    }

    protected function copyFile(string $from, string $to): void
    {
        $toDir = basename($to);

        if (!file_exists($toDir) && !mkdir($toDir, 0777, true) && !is_dir($toDir)) {
            throw new \RuntimeException(sprintf('Directory "%s" was not created', $toDir));
        }
        copy($from, $to);
    }
}
