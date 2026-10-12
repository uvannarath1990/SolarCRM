<?php require __DIR__ . '/../layout/header.php'; ?>

<?php
$roleColors = [
    'admin'            => '#6366f1',
    'manager'          => '#3b82f6',
    'operation_manager'=> '#0891b2',
    'accounts'         => '#059669',
    'sales_rep'        => '#d97706',
    'tele_caller'      => '#7c3aed',
    'installer'        => '#dc2626',
    'dealer'           => '#db2777',
    'documentation'    => '#64748b',
];
?>

<div class="d-flex justify-content-between align-items-center mt-3 mb-3 flex-wrap gap-2">
    <h5 class="mb-0 fw-bold"><i class="bi bi-person-gear me-2"></i>Users</h5>
    <div class="d-flex gap-2">
        <div class="btn-group btn-group-sm" role="group">
            <button class="btn btn-outline-secondary active" id="btnList">
                <i class="bi bi-list-ul me-1"></i>List
            </button>
            <button class="btn btn-outline-secondary" id="btnOrg">
                <i class="bi bi-diagram-3 me-1"></i>Org Chart
            </button>
        </div>
        <a href="<?= BASE_URL ?>/users/create" class="btn btn-primary btn-sm">
            <i class="bi bi-plus-lg me-1"></i>Add User
        </a>
    </div>
</div>

<!-- ── List view ─────────────────────────────────────────────────────────── -->
<div id="viewList">
<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Role</th>
                    <th>Reports To</th>
                    <th>Last Login</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($users as $u): ?>
            <tr>
                <td class="fw-semibold"><?= htmlspecialchars($u['name']) ?></td>
                <td><?= htmlspecialchars($u['email']) ?></td>
                <td><?= htmlspecialchars($u['phone'] ?? '—') ?></td>
                <td>
                    <?php $displayRole = $u['custom_role_label'] ?? null; ?>
                    <span class="badge text-white text-capitalize"
                          style="background:<?= $roleColors[$u['role']] ?? '#6c757d' ?>"
                          title="<?= $displayRole ? 'Permission level: ' . htmlspecialchars(str_replace('_',' ',$u['role'])) : '' ?>">
                        <?= htmlspecialchars($displayRole ?? str_replace('_', ' ', $u['role'])) ?>
                    </span>
                    <?php if ($displayRole): ?>
                    <br><small class="text-muted" style="font-size:.68rem"><?= htmlspecialchars(str_replace('_',' ',$u['role'])) ?> perms</small>
                    <?php endif; ?>
                </td>
                <td class="text-muted small"><?= $u['manager_name'] ? htmlspecialchars($u['manager_name']) : '—' ?></td>
                <td class="text-muted small"><?= $u['last_login'] ? formatDateTime($u['last_login']) : 'Never' ?></td>
                <td>
                    <?php if ($u['is_active']): ?>
                        <span class="badge bg-success">Active</span>
                    <?php else: ?>
                        <span class="badge bg-secondary">Inactive</span>
                    <?php endif; ?>
                </td>
                <td class="text-end">
                    <a href="<?= BASE_URL ?>/users/create?id=<?= $u['id'] ?>" class="btn btn-sm btn-outline-secondary me-1">
                        <i class="bi bi-pencil"></i>
                    </a>
                    <?php if ($u['id'] !== (int)($_SESSION['user_id'])): ?>
                    <button class="btn btn-sm <?= $u['is_active'] ? 'btn-outline-warning' : 'btn-outline-success' ?> toggle-active"
                            data-id="<?= $u['id'] ?>"
                            data-active="<?= $u['is_active'] ?>"
                            title="<?= $u['is_active'] ? 'Deactivate' : 'Activate' ?>">
                        <i class="bi <?= $u['is_active'] ? 'bi-person-slash' : 'bi-person-check' ?>"></i>
                    </button>
                    <button class="btn btn-sm btn-outline-danger delete-user"
                            data-id="<?= $u['id'] ?>"
                            data-name="<?= htmlspecialchars($u['name']) ?>"
                            title="Delete user">
                        <i class="bi bi-trash"></i>
                    </button>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($users)): ?>
            <tr><td colspan="8" class="text-center text-muted py-4">No users found.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
</div>

<!-- ── Org chart view ────────────────────────────────────────────────────── -->
<div id="viewOrg" style="display:none">
    <div class="card">
        <div class="card-body p-0">
            <div id="orgScroll" style="overflow:auto;width:100%;max-height:calc(100vh - 160px);padding:1.5rem 2rem">
                <div id="orgChart" class="org-tree" style="width:max-content;min-width:100%"></div>
            </div>
        </div>
    </div>
</div>

<!-- Embed user data for JS -->
<script>
const ORG_USERS = <?= json_encode(array_map(fn($u) => [
    'id'        => (int) $u['id'],
    'name'      => $u['name'],
    'role'      => $u['role'],
    'email'     => $u['email'],
    'is_active' => (bool) $u['is_active'],
    'manager_id'=> $u['manager_id'] ? (int) $u['manager_id'] : null,
    'edit_url'  => BASE_URL . '/users/create?id=' . $u['id'],
], $users), JSON_HEX_TAG) ?>;

const ROLE_COLORS = <?= json_encode($roleColors) ?>;

// ── View toggle ──────────────────────────────────────────────────────────
document.getElementById('btnList').addEventListener('click', function() {
    document.getElementById('viewList').style.display = '';
    document.getElementById('viewOrg').style.display  = 'none';
    this.classList.add('active');
    document.getElementById('btnOrg').classList.remove('active');
});
document.getElementById('btnOrg').addEventListener('click', function() {
    document.getElementById('viewList').style.display = 'none';
    document.getElementById('viewOrg').style.display  = '';
    this.classList.remove('active');
    document.getElementById('btnList').classList.remove('active');
    this.classList.add('active');
    buildOrgChart();
});

// ── Build org chart ──────────────────────────────────────────────────────
let orgBuilt = false;
function buildOrgChart() {
    if (orgBuilt) return;
    orgBuilt = true;

    const byId = {};
    ORG_USERS.forEach(u => byId[u.id] = { ...u, children: [] });

    const roots = [];
    ORG_USERS.forEach(u => {
        if (u.manager_id && byId[u.manager_id]) {
            byId[u.manager_id].children.push(byId[u.id]);
        } else {
            roots.push(byId[u.id]);
        }
    });

    const container = document.getElementById('orgChart');
    container.innerHTML = '';

    function makeCard(user) {
        const color    = ROLE_COLORS[user.role] ?? '#6c757d';
        const roleLabel = user.role.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
        const initials  = user.name.trim().split(/\s+/).map(w => w[0]).join('').toUpperCase().slice(0, 2);
        const div = document.createElement('div');
        div.className = 'org-node';
        div.style.opacity = user.is_active ? '1' : '0.55';
        div.onclick = () => window.location = user.edit_url;
        div.innerHTML =
            `<div class="org-avatar" style="background:${color}">${initials}</div>
             <div class="org-name" title="${escHtml(user.name)}">${escHtml(user.name)}</div>
             <div class="org-role" style="background:${color}20;color:${color}">${escHtml(roleLabel)}</div>
             ${!user.is_active ? '<div class="org-inactive-badge">Inactive</div>' : ''}`;
        return div;
    }

    function renderNode(user) {
        const hasChildren = user.children.length > 0;
        const wrap = document.createElement('div');
        wrap.className = 'org-node-wrap' + (hasChildren ? ' has-children' : '');

        wrap.appendChild(makeCard(user));

        if (hasChildren) {
            // Vertical stem down from this node
            const stem = document.createElement('div');
            stem.className = 'org-stem';
            wrap.appendChild(stem);

            // Children row
            const row = document.createElement('div');
            row.className = 'org-children';

            const sorted = user.children.slice().sort((a, b) => a.name.localeCompare(b.name));
            sorted.forEach(child => {
                const childWrap = document.createElement('div');
                childWrap.className = 'org-child-wrap';

                // Vertical drop line above child node
                const drop = document.createElement('div');
                drop.className = 'org-drop';
                childWrap.appendChild(drop);
                childWrap.appendChild(renderNode(child));
                row.appendChild(childWrap);
            });

            wrap.appendChild(row);

            // After DOM is ready, draw horizontal bar across children
            requestAnimationFrame(() => {
                if (row.children.length < 2) return;
                const first = row.children[0];
                const last  = row.children[row.children.length - 1];
                const rowRect   = row.getBoundingClientRect();
                const firstRect = first.getBoundingClientRect();
                const lastRect  = last.getBoundingClientRect();
                const left  = firstRect.left  + firstRect.width  / 2 - rowRect.left;
                const right = rowRect.right   - lastRect.right   + lastRect.width / 2;
                row.style.setProperty('--bar-left',  left  + 'px');
                row.style.setProperty('--bar-right', right + 'px');
                row.style.cssText += `;--bar-left:${left}px;--bar-right:${right}px`;
                // Apply via before pseudo using inline style override
                const bar = document.createElement('div');
                bar.style.cssText = `position:absolute;top:0;left:${left}px;right:${right}px;height:2px;background:#cbd5e1;pointer-events:none`;
                row.style.position = 'relative';
                row.appendChild(bar);
                // Hide the CSS ::before now that we have a real bar
                row.style.setProperty('--hide-before', '1');
            });
        }

        return wrap;
    }

    // Root level: render all root nodes side by side
    if (roots.length === 1) {
        container.appendChild(renderNode(roots[0]));
    } else {
        // Multiple roots: treat as siblings in a row
        const rootRow = document.createElement('div');
        rootRow.className = 'org-children';
        rootRow.style.justifyContent = 'center';
        roots.sort((a, b) => a.name.localeCompare(b.name)).forEach(root => {
            const cw = document.createElement('div');
            cw.className = 'org-child-wrap';
            cw.appendChild(renderNode(root));
            rootRow.appendChild(cw);
        });
        container.appendChild(rootRow);
    }
}

function escHtml(str) {
    return str.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// ── List actions ─────────────────────────────────────────────────────────
document.querySelectorAll('.toggle-active').forEach(btn => {
    btn.addEventListener('click', async function () {
        const id     = this.dataset.id;
        const active = this.dataset.active;
        const label  = active === '1' ? 'deactivate' : 'activate';
        if (!confirm(`Are you sure you want to ${label} this user?`)) return;
        const res = await apiFetch('/users/toggle-active', {
            method: 'POST',
            body: JSON.stringify({ id: parseInt(id) })
        });
        if (res.ok) location.reload();
    });
});

document.querySelectorAll('.delete-user').forEach(btn => {
    btn.addEventListener('click', async function () {
        const id   = this.dataset.id;
        const name = this.dataset.name;
        if (!confirm(`Permanently delete "${name}"? This cannot be undone.`)) return;
        const res = await apiFetch('/users/delete', {
            method: 'POST',
            body: JSON.stringify({ id: parseInt(id) })
        });
        if (res.ok) {
            this.closest('tr').remove();
        } else {
            alert(res.message || 'Could not delete user.');
        }
    });
});
</script>

<style>
/* ── Org tree container ───────────────────────────────────────────── */
.org-tree {
    display: flex;
    flex-direction: column;
    align-items: center;
}

/* ── Each level wrapper ───────────────────────────────────────────── */
.org-node-wrap {
    display: flex;
    flex-direction: column;
    align-items: center;
}

/* Vertical stem from node down to the horizontal bar */
.org-node-wrap.has-children > .org-node {
    margin-bottom: 0;
}
.org-node-wrap.has-children > .org-stem {
    width: 2px;
    height: 22px;
    background: #cbd5e1;
    flex-shrink: 0;
}

/* ── Children row ─────────────────────────────────────────────────── */
.org-children {
    display: flex;
    flex-direction: row;
    align-items: flex-start;
    position: relative;
}

/* Horizontal bar is drawn by JS (a real div), not ::before */

/* Each child's vertical drop */
.org-child-wrap {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 0 10px;
    position: relative;
}
.org-child-wrap > .org-drop {
    width: 2px;
    height: 22px;
    background: #cbd5e1;
}

/* ── Node card ────────────────────────────────────────────────────── */
.org-node {
    background: #fff;
    border: 1.5px solid #e2e8f0;
    border-radius: 12px;
    padding: 12px 14px;
    text-align: center;
    width: 130px;
    cursor: pointer;
    transition: box-shadow .18s, transform .18s;
    box-shadow: 0 1px 4px rgba(0,0,0,.07);
    flex-shrink: 0;
    user-select: none;
}
.org-node:hover {
    box-shadow: 0 4px 16px rgba(59,130,246,.2);
    transform: translateY(-2px);
}

.org-avatar {
    width: 46px; height: 46px;
    border-radius: 50%;
    color: #fff;
    font-weight: 700;
    font-size: 1rem;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 7px;
}
.org-name {
    font-weight: 600;
    font-size: .8rem;
    color: #1e293b;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.org-role {
    font-size: .62rem;
    border-radius: 20px;
    padding: 2px 8px;
    margin-top: 4px;
    display: inline-block;
    font-weight: 600;
    text-transform: capitalize;
    white-space: nowrap;
}
.org-inactive-badge {
    font-size: .58rem;
    color: #94a3b8;
    margin-top: 3px;
}
</style>

<?php require __DIR__ . '/../layout/footer.php'; ?>
