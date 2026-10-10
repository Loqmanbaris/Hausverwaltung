<?php

namespace App\Support\Updates;

/** Öffentlicher Schlüssel, mit dem die Signatur jedes Update-Pakets geprüft wird. Nur wer den passenden privaten Schlüssel hat, kann Pakete erstellen. */
class UpdateKey
{
    public const ID = '2026-10';

    public const PUBLIC_PEM = <<<'PEM'
-----BEGIN PUBLIC KEY-----
MIIBojANBgkqhkiG9w0BAQEFAAOCAY8AMIIBigKCAYEAwSXhTuWccE1o6rwYr8h5
dF643WfAQWJgPOJ109ryAT4W7T2M5NW5/0jcADzCN3PqrHk5oZSw/WBRxRHnXQPV
Ggtso347LTz3zRzRcHImW6ZXB4+6DH49OMmgwlXjiRYR/lIxz4SifJALbS735jKW
ZfUF3AcJmXAE7lrtowcyRyQOU0M4tD0LusqyV9+e9YromB10BL/q5gjStvVFMrqj
zsS1cG7ttI23GTHcLR7EVQs/J6FUNZ8D8jCooOoJu78TMMr0NXAa3kdMdlHph9tr
Eev+VTrLd92ZLK3WpFodrDAUBNSBozAJ3REG6dKRq7gEI+D32UVrtbRCw9xs9+g2
X6pvayQqCufzz5fNL9ykXBpeckH30pP+bFoS1HO/cBbyV4Ez8sVep29ZQuhjj3nU
IEUL+2GK+STb35iRDWnHnZ7naz/LenL18GLcmlsK1Qfkume+Ad3Ql+PKQfbLz/w0
r10o3z5TBDfXXE/HPr7GV9f5RoAn2HLija5DKmGgAsqnAgMBAAE=
-----END PUBLIC KEY-----
PEM;
}
