<?php
// Minimal autoloader for bundled dependencies.

spl_autoload_register(
    static function ( $class ): void {
        $prefix = 'Dompdf\\';
        if ( 0 !== strpos( $class, $prefix ) ) {
            return;
        }

        $relative = substr( $class, strlen( $prefix ) );
        $path     = __DIR__ . '/dompdf/dompdf/src/' . str_replace( '\\', '/', $relative ) . '.php';

        if ( file_exists( $path ) ) {
            require_once $path;
        }
    }
);

return true;
