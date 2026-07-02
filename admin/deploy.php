<?php

require_once __DIR__ . '/bootstrap.php';

hh_admin_require_login();

require_once __DIR__ . '/layout.php';

$serverIp = isset($_GET['ip']) ? trim((string) $_GET['ip']) : '35.212.252.17';
$repoUrl = isset($_GET['repo']) ? trim((string) $_GET['repo']) : 'https://github.com/lizeqilizeqi/handheld-hub-1.git';
$branch = isset($_GET['branch']) ? trim((string) $_GET['branch']) : 'main';
$appDir = '/opt/handheld-hub';

function hh_deploy_github_slug($repoUrl)
{
    $repoUrl = preg_replace('#\.git$#', '', trim($repoUrl));
    $repoUrl = preg_replace('#^https?://github.com/#', '', $repoUrl);
    return trim($repoUrl, '/');
}

function hh_deploy_raw_script_url($repoUrl, $branch)
{
    $slug = hh_deploy_github_slug($repoUrl);
    return 'https://raw.githubusercontent.com/' . $slug . '/' . rawurlencode($branch) . '/deploy/server-deploy.sh';
}

$rawScriptUrl = $repoUrl !== '' ? hh_deploy_raw_script_url($repoUrl, $branch) : 'https://raw.githubusercontent.com/你的用户名/handheld-hub/main/deploy/server-deploy.sh';

$firstLine = 'export REPO_URL="' . ($repoUrl !== '' ? $repoUrl : 'https://github.com/你的用户名/handheld-hub.git') . '"';

$firstDeploy = $firstLine . "\n"
    . 'curl -fsSL "' . $rawScriptUrl . '" | sudo REPO_URL="$REPO_URL" bash';

$firstDeployAlt = "sudo apt-get update && sudo apt-get install -y git\n"
    . 'sudo git clone "' . ($repoUrl !== '' ? $repoUrl : 'https://github.com/你/handheld-hub.git') . '" ' . $appDir . "\n"
    . 'sudo bash ' . $appDir . '/deploy/server-deploy.sh';

$updateCmd = 'sudo bash ' . $appDir . '/deploy/server-deploy.sh';

$syncSecrets = 'scp config.secrets.php YOUR_USER@' . $serverIp . ':' . $appDir . '/';
$syncImages = 'rsync -avz --progress storage/handhelds/ YOUR_USER@' . $serverIp . ':' . $appDir . '/storage/handhelds/';
$syncDb = 'docker compose -f docker-compose.prod.yml exec -T db mysqldump -u handheld -phandheld handheld_hub | ssh YOUR_USER@' . $serverIp . ' "docker compose -f ' . $appDir . '/docker-compose.prod.yml exec -T db mysql -u handheld -phandheld handheld_hub"';

hh_admin_layout_start('deploy');
?>

<div class="card info-box">
  <h3>Google Cloud 服务器部署</h3>
  <p>适用于 GCP Compute Engine（Ubuntu）。在 Console 点击 VM 的 <strong>SSH</strong> 打开浏览器终端，粘贴下方命令即可。</p>
  <p class="muted">你的实例：<code><?php echo hh_h($serverIp); ?></code> · 已打 <code>http-server</code> / <code>https-server</code> 标签（80/443 已放行）</p>
</div>

<div class="card">
  <h3>部署前准备（只需做一次）</h3>
  <ol>
    <li>把本项目推到 <strong>GitHub / GitLab</strong>（私有仓库也可以）</li>
    <li>GCP 项目里已启用 <strong>Google Drive API</strong>（Blogger 发图用）</li>
    <li>填写下方 Git 仓库地址，复制「首次部署」两行命令到 SSH 执行</li>
  </ol>
  <form method="get" class="form-inline" style="margin-top:1rem;">
    <label>Git 仓库 URL</label>
    <input type="url" name="repo" placeholder="https://github.com/you/handheld-hub.git" value="<?php echo hh_h($repoUrl); ?>" style="width:min(100%,520px);">
    <label>分支</label>
    <input type="text" name="branch" value="<?php echo hh_h($branch); ?>" style="width:120px;">
    <label>服务器 IP</label>
    <input type="text" name="ip" value="<?php echo hh_h($serverIp); ?>" style="width:160px;">
    <button type="submit" class="btn btn-secondary">生成命令</button>
  </form>
</div>

<div class="card">
  <h3>首次部署（SSH 里两行）</h3>
  <pre class="code-block" id="cmd-first"><?php echo hh_h($firstDeploy); ?></pre>
  <button type="button" class="btn btn-secondary btn-copy" data-target="cmd-first">复制</button>
  <p class="muted" style="margin-top:.75rem;">脚本会自动：装 Docker、加 swap、克隆代码、生成 config.local.php、启动 MySQL + PHP，并跑数据库迁移。站点监听 <strong>80</strong> 端口。</p>
  <p class="muted">若 curl 仍 404（私有仓库），用下方备用方案。</p>
</div>

<div class="card">
  <h3>首次部署 · 备用（git clone，私有仓库可用）</h3>
  <pre class="code-block" id="cmd-first-alt"><?php echo hh_h($firstDeployAlt); ?></pre>
  <button type="button" class="btn btn-secondary btn-copy" data-target="cmd-first-alt">复制</button>
</div>

<div class="card">
  <h3>以后更新（SSH 里一行）</h3>
  <pre class="code-block" id="cmd-update"><?php echo hh_h($updateCmd); ?></pre>
  <button type="button" class="btn btn-secondary btn-copy" data-target="cmd-update">复制</button>
  <p class="muted" style="margin-top:.75rem;">会 <code>git pull</code> 最新代码、重建容器、执行未跑过的 migration。不会覆盖 <code>config.local.php</code> 和 <code>config.secrets.php</code>。</p>
</div>

<div class="card">
  <h3>从本地迁移已有数据（可选）</h3>
  <p>若本地 Docker 里已有抓取数据、图片、OAuth 密钥，在<strong>本机 PowerShell</strong>执行（把 <code>YOUR_USER</code> 换成 SSH 用户名，GCP 浏览器 SSH 一般是你的 Google 账号对应用户）：</p>
  <p><strong>1. 密钥与 OAuth</strong></p>
  <pre class="code-block"><?php echo hh_h($syncSecrets); ?></pre>
  <p><strong>2. 掌机图片</strong></p>
  <pre class="code-block"><?php echo hh_h($syncImages); ?></pre>
  <p><strong>3. 数据库</strong>（在 handheld-hub 项目目录）</p>
  <pre class="code-block"><?php echo hh_h($syncDb); ?></pre>
  <p class="muted">迁移后把 <code>config.local.php</code> 里的 <code>app.base_url</code> 改为 <code>http://<?php echo hh_h($serverIp); ?></code>，并在 Google Cloud OAuth 里把 Redirect URI 改成 <code>http://<?php echo hh_h($serverIp); ?>/admin/blogger_oauth.php</code>。</p>
</div>

<div class="card">
  <h3>部署后访问</h3>
  <ul>
    <li>前台：<a href="http://<?php echo hh_h($serverIp); ?>/en/handhelds" target="_blank" rel="noopener">http://<?php echo hh_h($serverIp); ?>/en/handhelds</a></li>
    <li>后台：<a href="http://<?php echo hh_h($serverIp); ?>/admin/" target="_blank" rel="noopener">http://<?php echo hh_h($serverIp); ?>/admin/</a></li>
  </ul>
</div>

<script>
document.querySelectorAll('.btn-copy').forEach(function (btn) {
  btn.addEventListener('click', function () {
    var id = btn.getAttribute('data-target');
    var el = document.getElementById(id);
    if (!el) return;
    navigator.clipboard.writeText(el.textContent).then(function () {
      btn.textContent = '已复制';
      setTimeout(function () { btn.textContent = '复制'; }, 1500);
    });
  });
});
</script>

<?php
hh_admin_layout_end();
