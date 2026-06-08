<?php
function formatPhone(string $num): string {
    $s   = preg_replace('/\D/', '', $num);
    $len = strlen($s);
    if ($len === 11) {
        return substr($s,0,2).'-'.$s[2].'-'.substr($s,3,4).'-'.substr($s,7,4);
    }
    if ($len === 10) {
        return substr($s,0,2).'-'.substr($s,2,4).'-'.substr($s,6,4);
    }
    return $num;
}

function jsonResponse(array $data, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function chipLabel(mixed $val): string {
    if ($val === '1' || $val === 1) return 'Sim';
    if ($val === '0' || $val === 0) return 'Não';
    return '?';
}

function chipClass(mixed $val): string {
    if ($val === '1' || $val === 1) return 'bg-green-900/60 text-green-400 border-green-700';
    if ($val === '0' || $val === 0) return 'bg-red-900/60 text-red-400 border-red-700';
    return 'bg-gray-800 text-gray-500 border-gray-600';
}
