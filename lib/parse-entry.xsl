<?xml version="1.0" encoding="UTF-8" ?>
<xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform">
	
	<xsl:output encoding="UTF-8" indent="no" method="text" omit-xml-declaration="yes" />

	<xsl:template match="/">
		<xsl:apply-templates select="//entry"/>
	</xsl:template>
	
	<xsl:template match="*">
		<xsl:apply-templates select="* | text()"/>
		<xsl:text> </xsl:text>
	</xsl:template>

</xsl:stylesheet>