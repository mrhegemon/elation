{component name="desktop"}
{assign var="distinct_numbers" value=array_fill(1,15,'x')}
{assign var="distinct_numbers" value=array_keys($distinct_numbers)}
{assign var="x" value=shuffle($distinct_numbers)}
<li data-elation-component="desktop.Icon" 
		data-elation-args.type="SYS"
		data-elation-args.name="Sign In"
		data-elation-args.title="TheFind Credentials"
		data-elation-args.windowname="application_login"
		data-elation-args.windowtype="window.modal"
		data-elation-args.content="hack.login">
</li>
<li data-elation-component="desktop.Icon" 
		data-elation-args.type="SYS"
		data-elation-args.name="Settings"
		data-elation-args.title="Settings Panel"
		data-elation-args.windowname="application_admin"
		data-elation-args.windowtype="window.dialog"
		data-elation-args.content="hack.admin">
</li>
<li data-elation-component="desktop.Icon" 
		data-elation-args.type="API"
		data-elation-args.name="Data API Collections"
		data-elation-args.title="APICollection Explorer"
		data-elation-args.windowname="application_explorer"
		data-elation-args.windowtype="window.window"
		data-elation-args.content="hack.explorer">
</li>
<li data-elation-component="desktop.Icon" 
		data-elation-args.type="APP"
		data-elation-args.name="Terminal"
		data-elation-args.title="Terminal v0.1a"
		data-elation-args.windowname="application_terminal"
		data-elation-args.windowtype="window.window"
		data-elation-args.content="hack.terminal">
</li>
<li data-elation-component="desktop.Icon" 
		data-elation-args.type="WWW"
		data-elation-args.name="Config Editor"
		data-elation-args.title="TheFind Config Tool"
		data-elation-args.windowtype="window.iframe"
		data-elation-args.content="http://lazarus.newdev.thefind.com/admin?role=elation&adminaction=cobrand.load&cobrandname=base">
</li>
<li data-elation-component="desktop.Icon" 
		data-elation-args.type="WWW"
		data-elation-args.name="JIRA Tickets"
		data-elation-args.title="Ticket Shit"
		data-elation-args.windowtype="window.iframe"
		data-elation-args.content="https://thefind.atlassian.net/secure/Dashboard.jspa">
</li>
<li data-elation-component="desktop.Icon" 
		data-elation-args.type="WWW"
		data-elation-args.name="Inception"
		data-elation-args.title="IncognitOS - Desktop"
		data-elation-args.windowtype="window.iframe"
		data-elation-args.content="/hack?{foreach from=$distinct_numbers item="value"}{$value}{/foreach}">
</li>
<li data-elation-component="desktop.Icon" 
		data-elation-args.type="WWW"
		data-elation-args.name="Windows 3.11"
		data-elation-args.title="Windows 3.11 Emulation"
		data-elation-args.windowtype="window.iframe"
		data-elation-args.content="http://www.vrcade.io/win311?noheader=1">
</li>
<li data-elation-component="desktop.Icon" 
		data-elation-args.type="HTML"
		data-elation-args.name="Telnet"
		data-elation-args.title="Telnet by bioid"
		data-elation-args.windowname="application_telnet"
		data-elation-args.windowtype="window.window"
		data-elation-args.content="<telnet-element wsurl='ws://meobets.com:12355'></telnet-element>">
</li>
{*
<li data-elation-component="desktop.Icon" 
		data-elation-args.type="APP"
		data-elation-args.name="IRC"
		data-elation-args.title=""
		data-elation-args.windowname="application_network"
		data-elation-args.content="ui.example_infobox">
</li>
*}
<script src="/scripts/hack/bower/bower_components/webcomponentsjs/webcomponents.min.js"></script>
<link rel="import" href="/scripts/hack/bower/telnet-element.html">