<?php
namespace Dompdf;

/**
 * Lightweight options container for DOMPDF stub.
 */
class Options {
    /**
     * @var array<string, mixed>
     */
    private array $data = array();

    /**
     * Set a named option.
     *
     * @param string $name  Option name.
     * @param mixed  $value Option value.
     * @return void
     */
    public function set( string $name, $value ): void {
        $this->data[ $name ] = $value;
    }

    /**
     * Set the default font family.
     *
     * @param string $font Font family name.
     * @return void
     */
    public function setDefaultFont( string $font ): void {
        $this->set( 'default_font', $font );
    }

    /**
     * Retrieve an option value.
     *
     * @param string $name    Option name.
     * @param mixed  $default Default value.
     * @return mixed
     */
    public function get( string $name, $default = null ) {
        return $this->data[ $name ] ?? $default;
    }
}
