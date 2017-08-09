<?php


namespace Pam\Assets;


class Maintenance
{
    //Maintenance request codes, see 'devmastermap.maintenance_requests' table
    const TYPE_CHANGE = '10';
    const TYPE_CHANGE_SIGN_CONTENT = '11';
    const TYPE_CHANGE_STAFF_BOH_NAME = '12';
    const TYPE_CHANGE_SIGN_BRAILLE = '13';
    const TYPE_CHANGE_SIGN_SIGNLINK = '14';
    const TYPE_MAINTAIN_SIGN = '20';
    const TYPE_DAMAGED_SLATS = '21';
    const TYPE_DAMAGED_SIGN = '22';
    const TYPE_REINSTALL_SIGN = '23';
    const TYPE_REMOVE_SIGN = '25';
}