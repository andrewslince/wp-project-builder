<?php include 'define.php'; ?>
<!DOCTYPE html>
<!--[if lt IE 7]> <html class="lt-ie9 lt-ie8 lt-ie7"> <![endif]-->
<!--[if IE 7]> <html class="lt-ie9 lt-ie8"> <![endif]-->
<!--[if IE 8]> <html class="lt-ie9"> <![endif]-->
<!--[if gt IE 8]><!--><html><!--<![endif]-->
    <head>
        <meta charset="UTF-8">
        <title>wp project builder</title>
        <link href="css/style.css" rel="stylesheet" type="text/css" media="all" />
        <script type="text/javascript" src="js/functions.js"></script>
        <script type="text/javascript" src="js/main.js"></script>
    </head>
    <body>
        <header id="header">
            <h1>wp project builder</h1>
        </header>
        <section class="section">
            <div class="section-container">
                <h2 class="section-title">faça o build do seu projeto</h2>
                <p class="section-description">escolha o idioma do core do wp (português do Brasil ou inglês) e todos os plugins que você precisar para o seu projeto, e clique em build para gerar uma versão de um site em wordpress com as últimas versões.</p>
                <div id="build-container" class="section-content">
                    <ul id="accordion-menu">
                        <li id="build-config-core">
                            <h3 class="accordion-menu-title">core</h3>
                            <span class="accordion-menu-status">linguagem selecionada: <strong>nenhuma</strong></span>
                            <div class="accordion-menu-content">
                                <ul class="choice-options single-option">
                                    <?php foreach (getWpCoreList() as $core) : ?>
                                        <li><a class="option-description" onclick="WPProjectBuilder.selectOption(this);" href="javascript:void(0);"><?php echo $core['language']; ?></a></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        </li>
                        <li id="build-config-plugins">
                            <h3 class="accordion-menu-title">plugins</h3>
                            <span class="accordion-menu-status">plugins selecionados: <strong>nenhum</strong></span>
                            <div class="accordion-menu-content">
                                <div id="new-plugin">
                                    <h4>adicione um plugin</h4>
                                    <form action="/" method="GET" onsubmit="return WPProjectBuilder.addNewPlugin();">
                                        <label for="new-plugin-url">caso não encontre o plugin que deseja, adicione um novo copiando o endereço do mesmo no campo abaixo:</label>
                                        <input id="new-plugin-url" autocomplete="off" onkeyup="WPProjectBuilder.validateActiveNewPluginAction();" type="text" placeholder="ex.: https://wordpress.org/plugins/w3-total-cache" value="" />
                                        <input id="new-plugin-submit" type="submit" value="ok" class="" />
                                    </form>
                                </div>
                                <div id="plugin-list"></div>
                            </div>
                        </li>
                        <!-- <li id="build-config-project">
                            <h3 class="accordion-menu-title"><a href="javascript:void(0);">projeto</a></h3>
                        </li> -->
                    </ul>
                    <a href="javascript:void(0);" id="btn-build-project" class="btn btn-success">fazer build</a>
                </div>
                <script type="text/javascript">WPProjectBuilder.init();</script>
            </div>
        </section>
        <footer id="footer">powered by <a href="https://twitter.com/andrews_lince">@andrews_lince</a></footer>
    </body>
</html>