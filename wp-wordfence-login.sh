wp eval '
$users = get_users();
$data = [];
foreach ($users as $u) {
  $ts = get_user_meta($u->ID, "wfls-last-login", true);
  $ip = get_user_meta($u->ID, "wfls-last-login-ip", true);
  $role = implode(",", $u->roles);
  $data[] = [
    "id" => $u->ID,
    "user" => $u->user_login,
    "email" => $u->user_email,
    "role" => $role,
    "last_login" => $ts,
    "ip" => $ip,
  ];
}

# Sort by last_login descending
usort($data, function($a, $b) {
  return ($b["last_login"] ?? 0) <=> ($a["last_login"] ?? 0);
});

printf("%-3s %-15s %-25s %-14s %-20s %-20s\n", "ID", "User", "Email", "Role", "Last Login", "Last IP");
foreach ($data as $d) {
  $last_login = $d["last_login"] ? date("Y-m-d H:i:s", $d["last_login"]) : "-";
  printf("%-3d %-15s %-25s %-14s %-20s %-20s\n",
    $d["id"],
    $d["user"],
    $d["email"],
    $d["role"],
    $last_login,
    $d["ip"] ?: "-"
  );
}
'
