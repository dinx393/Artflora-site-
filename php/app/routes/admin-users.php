<?php
// Админка → «Сотрудники»: логины для сотрудников, только для старшего администратора.
// Сменили пароль, роль или отключили — все входы этого сотрудника сразу отменяются.

const ADMIN_USER_MSG = [
    'added' => 'Сотрудник добавлен. Передайте ему логин и пароль.',
    'saved' => 'Сохранено. Если менялся пароль или роль, сотруднику нужно войти заново.',
    'deleted' => 'Сотрудник удалён.',
    'bad-login' => 'Логин: от 3 до 40 латинских букв, цифр, точек, дефисов или подчёркиваний.',
    'bad-name' => 'Укажите имя (до 80 символов).',
    'bad-password' => 'Пароль: от 10 символов.',
    'taken' => 'Такой логин уже есть.',
];

function admin_user_redirect(string $msg): never
{
    Http::redirect('/admin/users?msg=' . $msg);
}

Http::get('/admin/users', function () {
    Auth::requireAdmin('users');
    $msg = is_string($_GET['msg'] ?? null) ? (ADMIN_USER_MSG[$_GET['msg']] ?? null) : null;
    return admin_page('users', [
        'title' => 'Сотрудники',
        'users' => Db::all('SELECT id, login, name, role, active, createdAt, lastLoginAt FROM admin_users ORDER BY active DESC, name'),
        'msg' => $msg,
        'msgOk' => in_array($_GET['msg'] ?? '', ['added', 'saved', 'deleted'], true),
    ]);
});

Http::post('/admin/users/add', function () {
    Auth::requireAdmin('users');
    Auth::checkCsrf();
    $login = mb_strtolower(Http::str('login', 60));
    $name = trim(Http::str('name', 120));
    $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
    $role = array_key_exists($_POST['role'] ?? '', Auth::ROLES) ? $_POST['role'] : 'staff';
    if (!preg_match('/^[a-z0-9._-]{3,40}$/', $login)) admin_user_redirect('bad-login');
    if ($name === '' || mb_strlen($name) > 80) admin_user_redirect('bad-name');
    if (mb_strlen($password) < 10 || strlen($password) > 200) admin_user_redirect('bad-password');
    try {
        Db::insert('admin_users', ['login' => $login, 'name' => $name, 'passwordHash' => Auth::hashPassword($password), 'role' => $role]);
    } catch (Throwable $e) {
        if (Db::isDuplicate($e)) admin_user_redirect('taken');
        throw $e;
    }
    admin_user_redirect('added');
});

Http::post('/admin/users/save', function () {
    Auth::requireAdmin('users');
    Auth::checkCsrf();
    $id = (int) Http::str('id');
    $u = Db::one('SELECT * FROM admin_users WHERE id = ?', [$id]) ?? Http::abort(404);
    $name = trim(Http::str('name', 120));
    $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
    $role = array_key_exists($_POST['role'] ?? '', Auth::ROLES) ? $_POST['role'] : $u['role'];
    $active = isset($_POST['active']) ? 1 : 0;
    if ($name === '' || mb_strlen($name) > 80) admin_user_redirect('bad-name');
    if ($password !== '' && (mb_strlen($password) < 10 || strlen($password) > 200)) admin_user_redirect('bad-password');
    $row = ['name' => $name, 'role' => $role, 'active' => $active];
    if ($password !== '') $row['passwordHash'] = Auth::hashPassword($password);
    Db::update('admin_users', $id, $row);
    if ($password !== '' || $role !== $u['role'] || !$active) Auth::endUserSessions($id);
    admin_user_redirect('saved');
});

Http::post('/admin/users/delete', function () {
    Auth::requireAdmin('users');
    Auth::checkCsrf();
    $id = (int) Http::str('id');
    Auth::endUserSessions($id);
    Db::exec('DELETE FROM admin_users WHERE id = ?', [$id]);
    admin_user_redirect('deleted');
});
