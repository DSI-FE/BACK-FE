<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class MiCorreo extends Mailable
{
    use Queueable, SerializesModels;

    public $nombre;
    public $fecha;
    public $codigo_generacion;
    public $numero_control;
    public $detalle;

    public function __construct( $nombre, $fecha, $codigo_generacion, $numero_control, $detalle)
    {
        $this->nombre = $nombre;
        $this->fecha = $fecha;
        $this->codigo_generacion = $codigo_generacion;
        $this->numero_control = $numero_control;
        $this->detalle = $detalle;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->view('mails.mi_correo')
                    ->subject('DTE - Ferreteria Flores');
    }
}
