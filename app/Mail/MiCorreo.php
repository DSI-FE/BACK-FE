<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class MiCorreo extends Mailable
{
    use Queueable, SerializesModels;

    public $emisor;
    public $nombre;
    public $fecha;
    public $codigo_generacion;
    public $numero_control;
    public $contenidoPDF;
    public $dte;
    public $json;

    public function __construct($emisor, $nombre, $fecha, $codigo_generacion, $numero_control, $contenidoPDF, $dte, $json)
    {
        $this->emisor = $emisor;
        $this->nombre = $nombre;
        $this->fecha = $fecha;
        $this->codigo_generacion = $codigo_generacion;
        $this->numero_control = $numero_control;
        $this->contenidoPDF = $contenidoPDF;
        $this->dte = $dte;
        $this->json = $json;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->view('mails.mi_correo')
                    ->subject($this->emisor)
                    ->attachData($this->contenidoPDF, 'DTE-'. $this->dte->codigo_generacion .'.pdf', [
                        'mime' => 'application/pdf',
                    ])
                    ->attachData($this->json, 'DTE-'.$this->dte->codigo_generacion.'.json', [
                        'mime' => 'application/json',
                    ]);
    }
}
