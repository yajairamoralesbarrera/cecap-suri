<?php

return [
    'required' => 'El campo :attribute es obligatorio.',
    'email' => 'El campo :attribute debe ser un correo electrónico válido.',
    'unique' => 'El valor de :attribute ya se encuentra registrado.',
    'file' => 'El campo :attribute debe ser un archivo válido.',
    'mimes' => 'El campo :attribute debe ser un archivo en formato: :values.',
    'uploaded' => 'No se pudo cargar :attribute. Verifique que el archivo sea PDF/JPG/PNG/WebP y que no exceda el límite permitido (máx. 25 MB en servidor; en la vista previa remota, archivos muy pesados pueden ser limitados por el túnel de prueba).',
    'max' => [
        'file' => 'El campo :attribute no debe superar :max KB.',
        'string' => 'El campo :attribute no debe superar :max caracteres.',
        'numeric' => 'El campo :attribute no debe ser mayor que :max.',
    ],
    'min' => [
        'string' => 'El campo :attribute debe tener al menos :min caracteres.',
        'numeric' => 'El campo :attribute debe ser al menos :min.',
    ],
    'attributes' => [
        'file_cedula' => 'la copia de Cédula / DIMEX',
        'file_atestado' => 'los Atestados Académicos',
        'pending_file_cedula' => 'la copia de Cédula / DIMEX',
        'pending_file_atestado' => 'los Atestados Académicos',
        'attachment_file' => 'el archivo adjunto',
        'credentials_file' => 'los Atestados Profesionales',
        'contract_pdf_file' => 'el contrato PDF',
        'file' => 'el documento',
        'new_version_file' => 'la nueva versión del documento',
    ],
];
