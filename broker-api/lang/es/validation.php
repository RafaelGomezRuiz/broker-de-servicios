<?php

/*
 * Mensajes de validación en español, solo para las reglas que usa el broker.
 * Si se usa una regla nueva y su mensaje sale en inglés, agrégala aquí.
 */
return [

    'array' => 'El campo :attribute debe ser una lista.',
    'date' => 'El campo :attribute debe ser una fecha válida.',
    'email' => 'El campo :attribute debe ser un correo válido.',
    'file' => 'El campo :attribute debe ser un archivo.',
    'in' => 'El valor de :attribute no es válido.',
    'integer' => 'El campo :attribute debe ser un número entero.',
    'max' => [
        'array' => 'El campo :attribute no puede tener más de :max elementos.',
        'file' => 'El archivo :attribute no puede pesar más de :max KB.',
        'numeric' => 'El campo :attribute no puede ser mayor que :max.',
        'string' => 'El campo :attribute no puede tener más de :max caracteres.',
    ],
    'min' => [
        'array' => 'El campo :attribute debe tener al menos :min elementos.',
        'numeric' => 'El campo :attribute debe ser al menos :min.',
    ],
    'required' => 'El campo :attribute es obligatorio.',
    'required_without' => 'El campo :attribute es obligatorio cuando no se envía :values.',
    'string' => 'El campo :attribute debe ser texto.',
    'after_or_equal' => 'El campo :attribute debe ser igual o posterior a :date.',

    'attributes' => [
        'para.*' => 'para',
        'cc.*' => 'cc',
        'cco.*' => 'cco',
        'adjuntos.*' => 'adjunto',
        'correos.*.para.*' => 'para',
        'correos.*.cc.*' => 'cc',
        'correos.*.cco.*' => 'cco',
        'correos.*.adjuntos.*' => 'adjunto',
        'correos.*.asunto' => 'asunto',
        'correos.*.html' => 'html',
        'correos.*.texto' => 'texto',
    ],

];
