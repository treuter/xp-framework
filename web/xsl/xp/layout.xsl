<?xml version="1.0" encoding="iso-8859-1"?>
<!--
 ! Layout stylesheet
 !
 ! $Id$
 !-->
<xsl:stylesheet
 version="1.0"
 xmlns:exsl="http://exslt.org/common"
 xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
 xmlns:func="http://exslt.org/functions"
 extension-element-prefixes="func"
>
  <xsl:include href="master.xsl"/>
  
  <xsl:variable name="navigation">
    <nav target="static">Home</nav>
    <nav target="about">About</nav>
    <nav target="documentation">Documentation</nav>
    <nav target="resources">Resources</nav>
    <nav target="devel">Development</nav>
  </xsl:variable>

  <!--
   ! Template that matches on the root node
   !
   ! @purpose  Define the site layout
   !-->
  <xsl:template match="/">
    <html>
      <head>
        <title>XP Framework | <xsl:value-of select="$__state"/> | <xsl:value-of select="$__page"/></title>
        <link rel="stylesheet" href="/{substring-before(concat($__state, '/'), '/')}.css"/>
      </head>
      <body>

        <!-- top navigation -->
        <table width="100%" border="0" cellspacing="0" cellpadding="2">
          <tr>
            <td colspan="6"><img src="/image/logo.png" width="202" height="60"/></td>
          </tr>
          <tr>
            <xsl:for-each select="exsl:node-set($navigation)/nav">
              <xsl:variable name="class">nav<xsl:if test="@target = $__state">active</xsl:if></xsl:variable>
              <td width="5%" class="{$class}">
                <a class="{$class}" href="/xml/{$__product}.{$__lang}/{@target}">
                  <xsl:value-of select="."/>
                </a>
              </td>
            </xsl:for-each>
            <td class="nav">&#160;</td>
            <td width="5%" class="nav" align="right">
              <input class="search" type="text" name="q" size="24"/>
            </td>
          </tr>
        </table>
        <br/>

        <!-- main content -->
        <table width="100%" border="0" cellspacing="0" cellpadding="2">
          <tr>
            <td valign="top">
              <xsl:call-template name="content"/>
            </td>
            <td width="2%">&#160;</td>
            <td width="15%" valign="top" nowrap="nowrap">
              <xsl:call-template name="context"/>
            </td>
          </tr>
        </table>
        <br/>

        <!-- footer -->
        <br/>
        <table width="100%" border="0" cellspacing="0" cellpadding="2" class="footer">
          <tr>
            <td><small>(c) 2001-2004 the XP team</small></td>
            <td align="right"><small>
              <a href="#credits">credits</a> |
              <a href="#feedback">feedback</a>
            </small></td>
          </tr>
        </table>
      </body>
    </html>
  </xsl:template>

</xsl:stylesheet>
