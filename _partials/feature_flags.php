<?php
// Single source of truth for client_settings feature flags.
// Each entry: 'column_name' => 'Display label shown to the master admin'.
// To add a new paid add-on:
//   1. Add a column to client_settings via SQL ALTER
//   2. Add the entry here
//   3. Reference the constant column name in your gating code
//   4. Put it on a tier: list it in billing_plans.php, or drag it onto one on
//      Master Admin → Plan features (and add a phrase in billing_feature_blurbs())
return [
    'feature_maps'            => 'Maps',
    'feature_postcode_lookup' => 'Postcode lookup',
    'feature_accounts'        => 'Accounts',
    'feature_sms'             => 'Texts',
    'feature_online_booking'  => 'Online booking',
];
