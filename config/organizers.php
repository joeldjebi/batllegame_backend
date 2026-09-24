<?php

return [
    // Anyone can open an organizer space (pending until the super-admin verifies it).
    // false: only the super-admin creates organizers.
    'self_signup' => (bool) env('ORGANIZER_SELF_SIGNUP', true),
];
