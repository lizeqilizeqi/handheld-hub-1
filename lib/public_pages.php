<?php

function hh_public_site_contact_email()
{
    $email = trim((string) hh_config_get('app.contact_email', ''));
    if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return $email;
    }
    return 'contact@oldman.dpdns.org';
}

function hh_public_site_owner()
{
    $owner = trim((string) hh_config_get('app.owner_name', ''));
    return $owner !== '' ? $owner : 'Handheld Hub';
}

function hh_public_static_pages()
{
    return array('about', 'privacy', 'contact', 'terms');
}

function hh_public_page_meta($locale, $slug)
{
    $locale = hh_public_locale($locale);
    $pages = hh_public_page_definitions($locale);
    if (!isset($pages[$slug])) {
        return null;
    }
    return $pages[$slug];
}

function hh_public_page_definitions($locale)
{
    $locale = in_array($locale, array('en', 'zh'), true) ? $locale : 'en';
    $site = $locale === 'zh' ? '掌机百科' : 'Handheld Hub';
    $email = hh_public_site_contact_email();
    $owner = hh_public_site_owner();
    $year = date('Y');

    if ($locale === 'zh') {
        return array(
            'about' => array(
                'title' => '关于我们',
                'description' => $site . ' 是专注于掌上游戏设备的独立百科网站，提供规格参数、发布信息与英文评测内容。',
                'sections' => array(
                    array(
                        'heading' => '我们是谁',
                        'html' => '<p>' . hh_h($site) . ' 是一个独立的掌上游戏设备（掌机）百科与资讯网站，面向全球英语与中文读者。我们整理各品牌掌机的发布时间、屏幕规格、硬件参数与使用场景介绍，帮助玩家快速了解市场上有哪些机型、各自适合什么用途。</p>',
                    ),
                    array(
                        'heading' => '内容来源与编辑方式',
                        'html' => '<p>本站数据库收录数百款掌机条目。基础规格与发布信息经人工整理与核对；英文介绍由编辑团队基于公开资料撰写或辅助生成，并在发布前审核。我们致力于提供清晰、可读的原创内容，而不是简单复制第三方网站全文。</p><p>若您发现信息有误或希望更正，请通过<a href="/zh/contact">联系我们</a>页面告知。</p>',
                    ),
                    array(
                        'heading' => '网站语言',
                        'html' => '<p>默认语言为英文（<code>/en/</code>），同时提供中文版本（<code>/zh/</code>）。您可随时通过页头语言菜单切换。</p>',
                    ),
                ),
            ),
            'privacy' => array(
                'title' => '隐私政策',
                'description' => '了解 ' . $site . ' 如何收集、使用 Cookie 及与第三方（含 Google AdSense）相关的数据处理方式。',
                'sections' => array(
                    array(
                        'heading' => '概述',
                        'html' => '<p>本隐私政策说明 ' . hh_h($owner) . '（以下简称「我们」）在您访问 ' . hh_h($site) . '（以下简称「本网站」）时如何处理信息。使用本网站即表示您同意本政策。</p><p>最后更新：' . $year . ' 年</p>',
                    ),
                    array(
                        'heading' => '我们收集的信息',
                        'html' => '<ul><li><strong>技术日志</strong>：服务器可能记录 IP 地址、浏览器类型、访问时间与请求的页面，用于安全与运维。</li><li><strong>Cookie</strong>：我们使用 Cookie 记住您的语言偏好（<code>hh_locale</code>）以及您是否接受 Cookie 提示（<code>hh_cookie_consent</code>）。</li><li><strong>联系信息</strong>：若您主动发邮件联系我们，我们会收到您提供的邮箱与信件内容。</li></ul>',
                    ),
                    array(
                        'heading' => 'Google AdSense 与第三方 Cookie',
                        'html' => '<p>本网站可能使用 <strong>Google AdSense</strong> 展示广告。Google 及其合作伙伴可能使用 Cookie 根据您对本网站及其他网站的访问展示个性化广告。</p><p>您可在 <a href="https://policies.google.com/technologies/ads" target="_blank" rel="noopener">Google 广告技术说明</a> 了解详情，或在 <a href="https://adssettings.google.com/" target="_blank" rel="noopener">Google 广告设置</a> 中管理个性化广告偏好。</p><p>我们不对第三方广告商的数据处理承担全部责任，但会选择符合政策的广告合作伙伴。</p>',
                    ),
                    array(
                        'heading' => '数据用途',
                        'html' => '<ul><li>提供与改进网站内容与导航体验</li><li>分析流量与防止滥用</li><li>在获得批准后展示相关广告（AdSense）</li><li>回复您的咨询邮件</li></ul>',
                    ),
                    array(
                        'heading' => '您的权利',
                        'html' => '<p>根据适用法律，您可请求访问、更正或删除我们持有的与您相关的个人数据。请发送邮件至 <a href="mailto:' . hh_h($email) . '">' . hh_h($email) . '</a>。</p>',
                    ),
                    array(
                        'heading' => '政策变更',
                        'html' => '<p>我们可能不时更新本政策，更新后的版本将发布在本页面。重大变更时我们会在网站显著位置提示。</p>',
                    ),
                ),
            ),
            'contact' => array(
                'title' => '联系我们',
                'description' => '联系 ' . $site . ' 团队：内容纠错、合作咨询或网站反馈。',
                'sections' => array(
                    array(
                        'heading' => '电子邮件',
                        'html' => '<p>如有内容纠错、版权疑问、合作或一般咨询，请发送邮件至：</p><p><a class="contact-email" href="mailto:' . hh_h($email) . '">' . hh_h($email) . '</a></p><p>我们通常会在 3–5 个工作日内回复。</p>',
                    ),
                    array(
                        'heading' => '反馈类型',
                        'html' => '<ul><li>掌机规格或发布日期错误</li><li>英文/中文内容质量问题</li><li>网站功能异常</li><li>广告或隐私政策相关问题</li></ul>',
                    ),
                    array(
                        'heading' => '网站信息',
                        'html' => '<p>网站名称：' . hh_h($site) . '<br>运营方：' . hh_h($owner) . '<br>网址：<a href="' . hh_h(hh_public_url()) . '">' . hh_h(hh_public_url()) . '</a></p>',
                    ),
                ),
            ),
            'terms' => array(
                'title' => '使用条款',
                'description' => $site . ' 网站使用条款与免责声明。',
                'sections' => array(
                    array(
                        'heading' => '接受条款',
                        'html' => '<p>访问或使用本网站，即表示您同意遵守本使用条款。若不同意，请停止使用本网站。</p>',
                    ),
                    array(
                        'heading' => '内容免责声明',
                        'html' => '<p>本网站掌机规格、价格与发布信息仅供参考，可能因厂商更新或市场变化而不完全准确。我们在合理范围内努力保持信息最新，但不对因使用本站信息而产生的任何损失承担责任。</p><p>部分产品图片与品牌标识属于各自权利人，本站仅作识别与介绍用途。</p>',
                    ),
                    array(
                        'heading' => '知识产权',
                        'html' => '<p>除另有说明外，本网站的版式、原创文字与整理后的数据结构归 ' . hh_h($owner) . ' 所有。未经授权，不得大规模抓取或复制本站内容用于商业目的。</p>',
                    ),
                    array(
                        'heading' => '外部链接',
                        'html' => '<p>本网站可能包含指向第三方网站（如 Blogger、厂商官网）的链接。我们不对第三方网站的内容或隐私做法负责。</p>',
                    ),
                    array(
                        'heading' => '条款变更',
                        'html' => '<p>我们保留随时修改本条款的权利。继续使用本网站即视为接受修订后的条款。</p>',
                    ),
                ),
            ),
        );
    }

    return array(
        'about' => array(
            'title' => 'About Us',
            'description' => $site . ' is an independent handheld gaming encyclopedia with specs, release dates, and English editorial content.',
            'sections' => array(
                array(
                    'heading' => 'Who we are',
                    'html' => '<p>' . hh_h($site) . ' is an independent encyclopedia for handheld gaming devices. We help readers worldwide discover consoles from brands like Anbernic, AYANEO, GPD, Retroid, Nintendo, and more—with release timelines, screen specs, hardware details, and practical buying context.</p>',
                ),
                array(
                    'heading' => 'How we create content',
                    'html' => '<p>Our catalog includes hundreds of handheld entries. Core specifications and release data are curated and checked; English articles are written or assisted by editors and reviewed before publication. We aim for readable, original reference content—not bulk copying of third-party articles.</p><p>Spotted an error? Please <a href="/en/contact">contact us</a>.</p>',
                ),
                array(
                    'heading' => 'Languages',
                    'html' => '<p>English is the default (<code>/en/</code>). A Chinese edition is available at <code>/zh/</code>. Use the language menu in the header to switch anytime.</p>',
                ),
            ),
        ),
        'privacy' => array(
            'title' => 'Privacy Policy',
            'description' => 'How ' . $site . ' handles cookies, logs, and third-party services including Google AdSense.',
            'sections' => array(
                array(
                    'heading' => 'Overview',
                    'html' => '<p>This Privacy Policy explains how ' . hh_h($owner) . ' ("we") handles information when you visit ' . hh_h($site) . ' ("the Site"). By using the Site, you agree to this policy.</p><p>Last updated: ' . $year . '</p>',
                ),
                array(
                    'heading' => 'Information we collect',
                    'html' => '<ul><li><strong>Server logs</strong>: IP address, browser type, access time, and pages requested—for security and operations.</li><li><strong>Cookies</strong>: We store your language preference (<code>hh_locale</code>) and cookie consent choice (<code>hh_cookie_consent</code>).</li><li><strong>Contact data</strong>: If you email us, we receive your address and message content.</li></ul>',
                ),
                array(
                    'heading' => 'Google AdSense & third-party cookies',
                    'html' => '<p>The Site may use <strong>Google AdSense</strong> to display ads. Google and its partners may use cookies to serve ads based on your visits to this and other websites.</p><p>Learn more at <a href="https://policies.google.com/technologies/ads" target="_blank" rel="noopener">Google Advertising Technologies</a> or manage preferences at <a href="https://adssettings.google.com/" target="_blank" rel="noopener">Google Ads Settings</a>.</p>',
                ),
                array(
                    'heading' => 'How we use data',
                    'html' => '<ul><li>Deliver and improve Site content and navigation</li><li>Monitor traffic and prevent abuse</li><li>Show relevant ads after AdSense approval</li><li>Respond to your inquiries</li></ul>',
                ),
                array(
                    'heading' => 'Your rights',
                    'html' => '<p>Depending on applicable law, you may request access, correction, or deletion of personal data we hold. Email <a href="mailto:' . hh_h($email) . '">' . hh_h($email) . '</a>.</p>',
                ),
                array(
                    'heading' => 'Changes',
                    'html' => '<p>We may update this policy from time to time. The current version is always published on this page.</p>',
                ),
            ),
        ),
        'contact' => array(
            'title' => 'Contact',
            'description' => 'Get in touch with the ' . $site . ' team for corrections, feedback, or partnerships.',
            'sections' => array(
                array(
                    'heading' => 'Email',
                    'html' => '<p>For content corrections, copyright questions, partnerships, or general feedback:</p><p><a class="contact-email" href="mailto:' . hh_h($email) . '">' . hh_h($email) . '</a></p><p>We typically reply within 3–5 business days.</p>',
                ),
                array(
                    'heading' => 'What to write about',
                    'html' => '<ul><li>Incorrect specs or release dates</li><li>English content quality issues</li><li>Site bugs or broken pages</li><li>Advertising or privacy questions</li></ul>',
                ),
                array(
                    'heading' => 'Site details',
                    'html' => '<p>Site: ' . hh_h($site) . '<br>Operator: ' . hh_h($owner) . '<br>URL: <a href="' . hh_h(hh_public_url()) . '">' . hh_h(hh_public_url()) . '</a></p>',
                ),
            ),
        ),
        'terms' => array(
            'title' => 'Terms of Use',
            'description' => 'Terms of use and disclaimers for ' . $site . '.',
            'sections' => array(
                array(
                    'heading' => 'Acceptance',
                    'html' => '<p>By accessing this Site, you agree to these Terms of Use. If you disagree, please stop using the Site.</p>',
                ),
                array(
                    'heading' => 'Content disclaimer',
                    'html' => '<p>Specifications, prices, and release information are provided for reference only and may change as manufacturers update products. We strive to keep data accurate but are not liable for decisions made based on Site content.</p><p>Product images and brand logos belong to their respective owners and are used for identification only.</p>',
                ),
                array(
                    'heading' => 'Intellectual property',
                    'html' => '<p>Unless otherwise noted, original text, layout, and curated data structures on this Site are owned by ' . hh_h($owner) . '. Bulk scraping or commercial republication without permission is not allowed.</p>',
                ),
                array(
                    'heading' => 'External links',
                    'html' => '<p>The Site may link to third-party websites (e.g. Blogger, manufacturer sites). We are not responsible for their content or privacy practices.</p>',
                ),
                array(
                    'heading' => 'Changes',
                    'html' => '<p>We may revise these terms at any time. Continued use of the Site constitutes acceptance of updated terms.</p>',
                ),
            ),
        ),
    );
}
