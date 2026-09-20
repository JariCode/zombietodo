<?php
// ================================
// task-meta.php — Jaetut apufunktiot tehtäväkorttien lisätiedoille
//
// Käytetään sekä tasks.php:ssä että partial-tasks.php:ssä.
// Ei muuta olemassa olevaa rakennetta — vain tuottaa kortin sisään
// tiedon päätehtävästä/alitehtävistä ja tuntisummasta.
// ================================

if (!function_exists('tm_clean')) {
    function tm_clean($v) { return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8'); }
}

// Tuntiluku siistiksi: 2.0 -> "2", 2.5 -> "2,5"
function tm_fmtHours($h) {
    $h = round((float)$h, 2);
    $s = rtrim(rtrim(number_format($h, 2, ',', ''), '0'), ',');
    return $s === '' ? '0' : $s;
}

// Hakee KAIKKI käyttäjän tehtävät (yhdestä operaatiosta) ja rakentaa hakurakenteet.
// Palauttaa: ['byId'=>[], 'children'=>[parent_id=>[lapset]], 'roots'=>[päätehtävät]]
function tm_loadAll($conn, $uid, $operationId) {
    $stmt = $conn->prepare('SELECT id, text, parent_id, hours, status FROM tasks WHERE user_id=? AND operation_id=? ORDER BY id ASC');
    $stmt->bind_param('ii', $uid, $operationId);
    $stmt->execute();
    $res = $stmt->get_result();
    $byId = []; $children = []; $roots = [];
    while ($r = $res->fetch_assoc()) {
        $r['parent_id'] = $r['parent_id'] !== null ? (int)$r['parent_id'] : null;
        $r['id'] = (int)$r['id'];
        $byId[$r['id']] = $r;
    }
    $stmt->close();
    foreach ($byId as $r) {
        if ($r['parent_id'] !== null && isset($byId[$r['parent_id']])) {
            $children[$r['parent_id']][] = $r['id'];
        } else {
            $roots[] = $r['id'];
        }
    }
    return ['byId' => $byId, 'children' => $children, 'roots' => $roots];
}

// Laskee tehtävän omat + kaikkien alitehtävien tunnit (rekursiivinen).
// $visited estää ikuisen luupin jos data sisältäisi kiertosilmukan.
function tm_subtreeHours($id, array $meta, array &$visited = []) {
    if (isset($visited[$id])) return 0; // Jo laskettu — ei lasketa uudestaan (silmukkasuoja)
    $visited[$id] = true;
    $sum = (float)($meta['byId'][$id]['hours'] ?? 0);
    foreach ($meta['children'][$id] ?? [] as $kid) {
        $sum += tm_subtreeHours($kid, $meta, $visited);
    }
    return $sum;
}

// Tulostaa yhden kortin lisätietolohkon: onko pää- vai alitehtävä + tunnit.
// Kutsutaan kortin .task-info sisällä, timestamp-lohkon jälkeen.
function tm_cardMeta($id, array $meta) {
    $t = $meta['byId'][$id] ?? null;
    if (!$t) return;
    $isSub = $t['parent_id'] !== null && isset($meta['byId'][$t['parent_id']]);
    $kids  = $meta['children'][$id] ?? [];
    $own   = tm_fmtHours($t['hours']);
    $total = tm_fmtHours(tm_subtreeHours($id, $meta));
    ?>
    <small class="task-meta">
        <?php if ($isSub): ?>
            <span class="tag-sub">Päätehtävä: <strong><?= tm_clean($meta['byId'][$t['parent_id']]['text']) ?></strong></span>
            <br>
        <?php endif; ?>
        Tunnit: <strong><?= $total ?> h</strong>
    </small>
    <?php if (!empty($kids)): ?>
        <div class="subtask-list">
            <span class="subtask-list-title">Alitehtävät:</span>
            <?php foreach ($kids as $kidId):
                $k = $meta['byId'][$kidId]; ?>
                <span class="subtask-chip status-<?= tm_clean($k['status']) ?>"><?= tm_clean($k['text']) ?> (<?= tm_fmtHours($k['hours']) ?> h)</span>
            <?php endforeach; ?>
        </div>
    <?php endif;
}

// Tulostaa <option>-listan päätehtävän valintaan muokkausmodalissa.
// $excludeId = muokattava tehtävä (ei voi olla oma päätehtävänsä).
function tm_parentOptions(array $meta, $excludeId = 0) {
    // Piirretään puu kevyesti sisennettynä (1 &nbsp; per taso), ohitetaan muokattava tehtävä ja sen alipuu.
    // Alitehtävät erottuvat päätehtävistä myös ↳-etuliitteellä (depth > 0).
    $walk = function($ids, $depth) use (&$walk, $meta, $excludeId) {
        foreach ($ids as $id) {
            if ((int)$id === (int)$excludeId) continue; // Ei itseään
            $indent = str_repeat('&nbsp;', $depth);
            $prefix = $depth > 0 ? '↳ ' : '';
            echo '<option value="' . (int)$id . '" data-id="' . (int)$id . '">' . $indent . $prefix . tm_clean($meta['byId'][$id]['text']) . '</option>';
            if (!empty($meta['children'][$id])) {
                $walk($meta['children'][$id], $depth + 2);
            }
        }
    };
    $walk($meta['roots'], 0);
}