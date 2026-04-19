<?php

return [
    'exception_message' => 'Poruka izuzetka: :message',
    'exception_trace' => 'Trag izuzetka: :trace',
    'exception_message_title' => 'Poruka izuzetka',
    'exception_trace_title' => 'Trag izuzetka',

    'backup_failed_subject' => 'Sigurnosna kopija za :application_name nije uspjela',
    'backup_failed_body' => 'Važno: došlo je do greške pri izradi sigurnosne kopije za :application_name',

    'backup_successful_subject' => 'Nova sigurnosna kopija za :application_name je uspješno kreirana',
    'backup_successful_subject_title' => 'Nova sigurnosna kopija je uspješna!',
    'backup_successful_body' => 'Dobre vijesti, nova sigurnosna kopija za :application_name je uspješno kreirana na disku :disk_name.',

    'cleanup_failed_subject' => 'Čišćenje sigurnosnih kopija za :application_name nije uspjelo.',
    'cleanup_failed_body' => 'Došlo je do greške pri čišćenju sigurnosnih kopija za :application_name',

    'cleanup_successful_subject' => 'Čišćenje sigurnosnih kopija za :application_name je uspješno',
    'cleanup_successful_subject_title' => 'Čišćenje sigurnosnih kopija je uspješno!',
    'cleanup_successful_body' => 'Čišćenje sigurnosnih kopija za :application_name na disku :disk_name je uspješno završeno.',

    'healthy_backup_found_subject' => 'Sigurnosne kopije za :application_name na disku :disk_name su ispravne',
    'healthy_backup_found_subject_title' => 'Sigurnosne kopije za :application_name su ispravne',
    'healthy_backup_found_body' => 'Sigurnosne kopije za :application_name se smatraju ispravnim. Odlično!',

    'unhealthy_backup_found_subject' => 'Važno: sigurnosne kopije za :application_name nisu ispravne',
    'unhealthy_backup_found_subject_title' => 'Važno: sigurnosne kopije za :application_name nisu ispravne. :problem',
    'unhealthy_backup_found_body' => 'Sigurnosne kopije za :application_name na disku :disk_name nisu ispravne.',
    'unhealthy_backup_found_not_reachable' => 'Odredište sigurnosne kopije nije dostupno. :error',
    'unhealthy_backup_found_empty' => 'Ne postoje sigurnosne kopije ove aplikacije.',
    'unhealthy_backup_found_old' => 'Najnovija sigurnosna kopija napravljena :date smatra se prestarom.',
    'unhealthy_backup_found_unknown' => 'Žao nam je, tačan razlog nije moguće utvrditi.',
    'unhealthy_backup_found_full' => 'Sigurnosne kopije koriste previše prostora. Trenutna potrošnja je :disk_usage, što je više od dozvoljenog limita :disk_limit.',
];
