<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260222231548 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE `admin` (id INT AUTO_INCREMENT NOT NULL, username VARCHAR(180) NOT NULL, roles JSON NOT NULL, password VARCHAR(255) NOT NULL, UNIQUE INDEX UNIQ_880E0D76F85E0677 (username), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE banco (id INT AUTO_INCREMENT NOT NULL, categoria_bn INT DEFAULT NULL, importe_bn DOUBLE PRECISION NOT NULL, concepto_bn LONGTEXT NOT NULL, fecha_bn DATE NOT NULL, timestamp_bn DATETIME NOT NULL, conciliado TINYINT(1) DEFAULT NULL, INDEX IDX_77DEE1D130B640AF (categoria_bn), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE cestas (id INT AUTO_INCREMENT NOT NULL, prespuesto_cs_id INT DEFAULT NULL, user_admin_id INT DEFAULT NULL, fecha_cs DATE NOT NULL, importe_tot_cs DOUBLE PRECISION NOT NULL, descuento_cs DOUBLE PRECISION NOT NULL, tipopago_cs VARCHAR(15) NOT NULL, numticket_cs VARCHAR(55) NOT NULL, estado_cs INT NOT NULL, timestamp_cs DATETIME NOT NULL, fecha_fin_cs DATE DEFAULT NULL, INDEX IDX_F1E891F451BC2A97 (prespuesto_cs_id), INDEX IDX_F1E891F484A66610 (user_admin_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE clientes (id INT AUTO_INCREMENT NOT NULL, nombre_cl VARCHAR(100) NOT NULL, apellidos_cl VARCHAR(150) DEFAULT NULL, ciudad_cl VARCHAR(100) DEFAULT NULL, direccion_cl VARCHAR(255) DEFAULT NULL, telefono1_cl VARCHAR(9) DEFAULT NULL, telefono2_cl VARCHAR(9) DEFAULT NULL, email_cl VARCHAR(100) DEFAULT NULL, timestampalta_cl DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, dni VARCHAR(9) DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE consultas (id INT AUTO_INCREMENT NOT NULL, nombre VARCHAR(255) DEFAULT NULL, email VARCHAR(255) DEFAULT NULL, telefono VARCHAR(20) DEFAULT NULL, pregunta VARCHAR(2500) DEFAULT NULL, timestamp DATETIME DEFAULT NULL, atencion TINYINT(1) DEFAULT NULL, presupuesto_ai JSON DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE detallecesta (id INT AUTO_INCREMENT NOT NULL, cesta_dc_id INT NOT NULL, producto_dc_id INT NOT NULL, cantidad_dc INT NOT NULL, pvp_dc DOUBLE PRECISION NOT NULL, descuento_dc DOUBLE PRECISION NOT NULL, timestamp_dc DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, precio_dc DOUBLE PRECISION DEFAULT NULL, texto_dc VARCHAR(255) DEFAULT NULL, coste_actualizado_por_factura TINYINT(1) DEFAULT NULL, factura_origen VARCHAR(255) DEFAULT NULL, fecha_actualizacion_coste DATETIME DEFAULT NULL, coste_anterior DOUBLE PRECISION DEFAULT NULL, INDEX IDX_A3E82772787386AA (cesta_dc_id), INDEX IDX_A3E827725A246A35 (producto_dc_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE economicpresu (id INT AUTO_INCREMENT NOT NULL, idpresu_eco_id INT NOT NULL, banco_eco_id INT DEFAULT NULL, concepto_eco VARCHAR(100) DEFAULT NULL, importe_eco DOUBLE PRECISION DEFAULT NULL, debehaber_eco VARCHAR(1) DEFAULT NULL, aplica_eco VARCHAR(1) DEFAULT NULL, estado_eco VARCHAR(1) DEFAULT NULL, timestamp DATETIME DEFAULT NULL, INDEX IDX_45996642C5D8D2D (idpresu_eco_id), UNIQUE INDEX UNIQ_45996642249EB7E8 (banco_eco_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE efectivo (id INT AUTO_INCREMENT NOT NULL, presupuestoef_id INT DEFAULT NULL, concepto_ef VARCHAR(255) NOT NULL, fecha_ef DATE NOT NULL, importe_ef DOUBLE PRECISION NOT NULL, timestamp_ef DATETIME NOT NULL, tipoEf INT DEFAULT NULL, INDEX IDX_94D7FEC0B438A8E4 (tipoEf), INDEX IDX_94D7FEC0C3DDDF5B (presupuestoef_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE email (id INT AUTO_INCREMENT NOT NULL, subject VARCHAR(255) NOT NULL, body LONGTEXT NOT NULL, from_email VARCHAR(255) NOT NULL, is_read TINYINT(1) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE encuesta (id INT AUTO_INCREMENT NOT NULL, fecha DATE DEFAULT NULL, cliente VARCHAR(255) DEFAULT NULL, p1 VARCHAR(10) DEFAULT NULL, p2 VARCHAR(10) DEFAULT NULL, p3 VARCHAR(10) DEFAULT NULL, p4 VARCHAR(10) DEFAULT NULL, p5 LONGTEXT DEFAULT NULL COMMENT \'(DC2Type:array)\', p6 LONGTEXT DEFAULT NULL COMMENT \'(DC2Type:array)\', p7 VARCHAR(10) DEFAULT NULL, p8 VARCHAR(10) DEFAULT NULL, p9 VARCHAR(10) DEFAULT NULL, p10 VARCHAR(10) DEFAULT NULL, p11 LONGTEXT DEFAULT NULL COMMENT \'(DC2Type:array)\', PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE estadocestas (id INT AUTO_INCREMENT NOT NULL, descripcion_ec VARCHAR(255) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE forecast (id INT AUTO_INCREMENT NOT NULL, tipo_fr_id INT NOT NULL, banco_id INT DEFAULT NULL, concepto_fr VARCHAR(255) NOT NULL, fecha_fr DATE NOT NULL, importe_fr DOUBLE PRECISION NOT NULL, origen_fr VARCHAR(50) NOT NULL, fijovar_fr VARCHAR(1) NOT NULL, estado_fr VARCHAR(10) NOT NULL, timestamp DATETIME DEFAULT NULL, INDEX IDX_2A9C78448EDA9B0D (tipo_fr_id), INDEX IDX_2A9C7844CC04A73E (banco_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE image (id INT AUTO_INCREMENT NOT NULL, post_id INT NOT NULL, file_path VARCHAR(255) DEFAULT NULL, image_type VARCHAR(255) DEFAULT NULL, alt VARCHAR(255) DEFAULT NULL, INDEX IDX_C53D045F4B89032C (post_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE logs (id INT AUTO_INCREMENT NOT NULL, id_log INT NOT NULL, fecha DATETIME NOT NULL, descripcion VARCHAR(255) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE mano_obra (id INT AUTO_INCREMENT NOT NULL, presupuesto_mo_id INT NOT NULL, categoria_mo_id INT DEFAULT NULL, tipo_mo VARCHAR(50) NOT NULL, texto_mo LONGTEXT DEFAULT NULL, coste DOUBLE PRECISION DEFAULT NULL, INDEX IDX_C0A6B2F4D3F5157B (presupuesto_mo_id), INDEX IDX_C0A6B2F45CF27655 (categoria_mo_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE pagos (id INT AUTO_INCREMENT NOT NULL, cesta_id INT NOT NULL, banco_pg_id INT DEFAULT NULL, efectivo_pg_id INT DEFAULT NULL, fecha_pg DATETIME DEFAULT NULL, importe_pg DOUBLE PRECISION DEFAULT NULL, tipo_pg VARCHAR(25) DEFAULT NULL, INDEX IDX_DA9B0DFF79B6AE57 (cesta_id), INDEX IDX_DA9B0DFFD1DAB94C (banco_pg_id), INDEX IDX_DA9B0DFF2D4C4952 (efectivo_pg_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE post (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(255) DEFAULT NULL, slug VARCHAR(255) NOT NULL, meta_description VARCHAR(255) DEFAULT NULL, header_h1 VARCHAR(255) DEFAULT NULL, header_h2 VARCHAR(255) DEFAULT NULL, header_h3 VARCHAR(255) DEFAULT NULL, header_h4 VARCHAR(255) DEFAULT NULL, content LONGTEXT DEFAULT NULL, published_at DATETIME DEFAULT NULL, is_published TINYINT(1) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE presupuestos (id INT AUTO_INCREMENT NOT NULL, estado_pe_id INT NOT NULL, user_pe_id INT NOT NULL, ticket_id INT DEFAULT NULL, cliente_pe_id INT DEFAULT NULL, fechaini_pe DATE DEFAULT NULL, costetot_pe DOUBLE PRECISION DEFAULT NULL, importetot_pe DOUBLE PRECISION NOT NULL, descuaeto_pe DOUBLE PRECISION NOT NULL, tipopagotot_pe VARCHAR(20) DEFAULT NULL, importesnal_pe DOUBLE PRECISION DEFAULT NULL, tipopagosnal_pe VARCHAR(20) DEFAULT NULL, manoobra_pe LONGTEXT DEFAULT NULL, timestamp_mod_pe DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, importemanoobra DOUBLE PRECISION DEFAULT NULL, impmanoobra_pagado DOUBLE PRECISION DEFAULT NULL, INDEX IDX_4CF2F0DA0F38A33 (estado_pe_id), INDEX IDX_4CF2F0D48CAF799 (user_pe_id), UNIQUE INDEX UNIQ_4CF2F0D700047D2 (ticket_id), INDEX IDX_4CF2F0D798551F2 (cliente_pe_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE presupuestos_lead (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(255) DEFAULT NULL, nombre VARCHAR(255) DEFAULT NULL, tipo_reforma VARCHAR(50) DEFAULT NULL, fecha_pdf DATETIME NOT NULL, email1_enviado TINYINT(1) DEFAULT NULL, email2_enviado TINYINT(1) DEFAULT NULL, seguimiento_activo TINYINT(1) DEFAULT NULL, pdf_descargas INT DEFAULT NULL, ultimo_evento DATETIME DEFAULT NULL, json_presupuesto JSON DEFAULT NULL, total DOUBLE PRECISION DEFAULT NULL, mano_obra DOUBLE PRECISION DEFAULT NULL, materiales DOUBLE PRECISION DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE productos (id INT AUTO_INCREMENT NOT NULL, tipo_pd_id INT DEFAULT NULL, descripcion_pd LONGTEXT DEFAULT NULL, precio_pd DOUBLE PRECISION NOT NULL, pvp_pd DOUBLE PRECISION NOT NULL, stock_pd SMALLINT NOT NULL, fec_alta_pd DATETIME NOT NULL, obsoleto TINYINT(1) DEFAULT NULL, INDEX IDX_767490E61408F16C (tipo_pd_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE stock_item (id INT AUTO_INCREMENT NOT NULL, producto_id INT NOT NULL, cantidad INT NOT NULL, precio_unitario DOUBLE PRECISION NOT NULL, fecha_entrada DATETIME NOT NULL, proveedor VARCHAR(255) DEFAULT NULL, factura_id VARCHAR(255) DEFAULT NULL, INDEX IDX_6017DDA7645698E (producto_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE texto_mano_obra (id INT AUTO_INCREMENT NOT NULL, tipo_xo_id INT NOT NULL, descripcion_xo LONGTEXT NOT NULL, resumen_xo VARCHAR(50) NOT NULL, INDEX IDX_39AA516FF3793DAC (tipo_xo_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE tipo_mano_obra (id INT AUTO_INCREMENT NOT NULL, tipo_tm VARCHAR(50) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE tipoproducto (id INT AUTO_INCREMENT NOT NULL, decripcion_tp VARCHAR(255) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE tiposmovimiento (id INT AUTO_INCREMENT NOT NULL, descripcion_tm VARCHAR(255) NOT NULL, patron_busqueda VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE uso_de_stock (id INT AUTO_INCREMENT NOT NULL, producto_id INT NOT NULL, stock_item_id INT NOT NULL, presupuesto_id INT DEFAULT NULL, cantidad INT NOT NULL, fecha DATETIME NOT NULL, comentario VARCHAR(255) DEFAULT NULL, INDEX IDX_D018037F7645698E (producto_id), INDEX IDX_D018037FBC942FD (stock_item_id), INDEX IDX_D018037F90119F0F (presupuesto_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE banco ADD CONSTRAINT FK_77DEE1D130B640AF FOREIGN KEY (categoria_bn) REFERENCES tiposmovimiento (id)');
        $this->addSql('ALTER TABLE cestas ADD CONSTRAINT FK_F1E891F451BC2A97 FOREIGN KEY (prespuesto_cs_id) REFERENCES presupuestos (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE cestas ADD CONSTRAINT FK_F1E891F484A66610 FOREIGN KEY (user_admin_id) REFERENCES `admin` (id)');
        $this->addSql('ALTER TABLE detallecesta ADD CONSTRAINT FK_A3E82772787386AA FOREIGN KEY (cesta_dc_id) REFERENCES cestas (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE detallecesta ADD CONSTRAINT FK_A3E827725A246A35 FOREIGN KEY (producto_dc_id) REFERENCES productos (id)');
        $this->addSql('ALTER TABLE economicpresu ADD CONSTRAINT FK_45996642C5D8D2D FOREIGN KEY (idpresu_eco_id) REFERENCES presupuestos (id)');
        $this->addSql('ALTER TABLE economicpresu ADD CONSTRAINT FK_45996642249EB7E8 FOREIGN KEY (banco_eco_id) REFERENCES banco (id)');
        $this->addSql('ALTER TABLE efectivo ADD CONSTRAINT FK_94D7FEC0B438A8E4 FOREIGN KEY (tipoEf) REFERENCES tiposmovimiento (id)');
        $this->addSql('ALTER TABLE efectivo ADD CONSTRAINT FK_94D7FEC0C3DDDF5B FOREIGN KEY (presupuestoef_id) REFERENCES presupuestos (id)');
        $this->addSql('ALTER TABLE forecast ADD CONSTRAINT FK_2A9C78448EDA9B0D FOREIGN KEY (tipo_fr_id) REFERENCES tiposmovimiento (id)');
        $this->addSql('ALTER TABLE forecast ADD CONSTRAINT FK_2A9C7844CC04A73E FOREIGN KEY (banco_id) REFERENCES banco (id)');
        $this->addSql('ALTER TABLE image ADD CONSTRAINT FK_C53D045F4B89032C FOREIGN KEY (post_id) REFERENCES post (id)');
        $this->addSql('ALTER TABLE mano_obra ADD CONSTRAINT FK_C0A6B2F4D3F5157B FOREIGN KEY (presupuesto_mo_id) REFERENCES presupuestos (id)');
        $this->addSql('ALTER TABLE mano_obra ADD CONSTRAINT FK_C0A6B2F45CF27655 FOREIGN KEY (categoria_mo_id) REFERENCES tipo_mano_obra (id)');
        $this->addSql('ALTER TABLE pagos ADD CONSTRAINT FK_DA9B0DFF79B6AE57 FOREIGN KEY (cesta_id) REFERENCES cestas (id)');
        $this->addSql('ALTER TABLE pagos ADD CONSTRAINT FK_DA9B0DFFD1DAB94C FOREIGN KEY (banco_pg_id) REFERENCES banco (id)');
        $this->addSql('ALTER TABLE pagos ADD CONSTRAINT FK_DA9B0DFF2D4C4952 FOREIGN KEY (efectivo_pg_id) REFERENCES efectivo (id)');
        $this->addSql('ALTER TABLE presupuestos ADD CONSTRAINT FK_4CF2F0DA0F38A33 FOREIGN KEY (estado_pe_id) REFERENCES estadocestas (id)');
        $this->addSql('ALTER TABLE presupuestos ADD CONSTRAINT FK_4CF2F0D48CAF799 FOREIGN KEY (user_pe_id) REFERENCES `admin` (id)');
        $this->addSql('ALTER TABLE presupuestos ADD CONSTRAINT FK_4CF2F0D700047D2 FOREIGN KEY (ticket_id) REFERENCES cestas (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE presupuestos ADD CONSTRAINT FK_4CF2F0D798551F2 FOREIGN KEY (cliente_pe_id) REFERENCES clientes (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE productos ADD CONSTRAINT FK_767490E61408F16C FOREIGN KEY (tipo_pd_id) REFERENCES tipoproducto (id)');
        $this->addSql('ALTER TABLE stock_item ADD CONSTRAINT FK_6017DDA7645698E FOREIGN KEY (producto_id) REFERENCES productos (id)');
        $this->addSql('ALTER TABLE texto_mano_obra ADD CONSTRAINT FK_39AA516FF3793DAC FOREIGN KEY (tipo_xo_id) REFERENCES tipo_mano_obra (id)');
        $this->addSql('ALTER TABLE uso_de_stock ADD CONSTRAINT FK_D018037F7645698E FOREIGN KEY (producto_id) REFERENCES productos (id)');
        $this->addSql('ALTER TABLE uso_de_stock ADD CONSTRAINT FK_D018037FBC942FD FOREIGN KEY (stock_item_id) REFERENCES stock_item (id)');
        $this->addSql('ALTER TABLE uso_de_stock ADD CONSTRAINT FK_D018037F90119F0F FOREIGN KEY (presupuesto_id) REFERENCES presupuestos (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE banco DROP FOREIGN KEY FK_77DEE1D130B640AF');
        $this->addSql('ALTER TABLE cestas DROP FOREIGN KEY FK_F1E891F451BC2A97');
        $this->addSql('ALTER TABLE cestas DROP FOREIGN KEY FK_F1E891F484A66610');
        $this->addSql('ALTER TABLE detallecesta DROP FOREIGN KEY FK_A3E82772787386AA');
        $this->addSql('ALTER TABLE detallecesta DROP FOREIGN KEY FK_A3E827725A246A35');
        $this->addSql('ALTER TABLE economicpresu DROP FOREIGN KEY FK_45996642C5D8D2D');
        $this->addSql('ALTER TABLE economicpresu DROP FOREIGN KEY FK_45996642249EB7E8');
        $this->addSql('ALTER TABLE efectivo DROP FOREIGN KEY FK_94D7FEC0B438A8E4');
        $this->addSql('ALTER TABLE efectivo DROP FOREIGN KEY FK_94D7FEC0C3DDDF5B');
        $this->addSql('ALTER TABLE forecast DROP FOREIGN KEY FK_2A9C78448EDA9B0D');
        $this->addSql('ALTER TABLE forecast DROP FOREIGN KEY FK_2A9C7844CC04A73E');
        $this->addSql('ALTER TABLE image DROP FOREIGN KEY FK_C53D045F4B89032C');
        $this->addSql('ALTER TABLE mano_obra DROP FOREIGN KEY FK_C0A6B2F4D3F5157B');
        $this->addSql('ALTER TABLE mano_obra DROP FOREIGN KEY FK_C0A6B2F45CF27655');
        $this->addSql('ALTER TABLE pagos DROP FOREIGN KEY FK_DA9B0DFF79B6AE57');
        $this->addSql('ALTER TABLE pagos DROP FOREIGN KEY FK_DA9B0DFFD1DAB94C');
        $this->addSql('ALTER TABLE pagos DROP FOREIGN KEY FK_DA9B0DFF2D4C4952');
        $this->addSql('ALTER TABLE presupuestos DROP FOREIGN KEY FK_4CF2F0DA0F38A33');
        $this->addSql('ALTER TABLE presupuestos DROP FOREIGN KEY FK_4CF2F0D48CAF799');
        $this->addSql('ALTER TABLE presupuestos DROP FOREIGN KEY FK_4CF2F0D700047D2');
        $this->addSql('ALTER TABLE presupuestos DROP FOREIGN KEY FK_4CF2F0D798551F2');
        $this->addSql('ALTER TABLE productos DROP FOREIGN KEY FK_767490E61408F16C');
        $this->addSql('ALTER TABLE stock_item DROP FOREIGN KEY FK_6017DDA7645698E');
        $this->addSql('ALTER TABLE texto_mano_obra DROP FOREIGN KEY FK_39AA516FF3793DAC');
        $this->addSql('ALTER TABLE uso_de_stock DROP FOREIGN KEY FK_D018037F7645698E');
        $this->addSql('ALTER TABLE uso_de_stock DROP FOREIGN KEY FK_D018037FBC942FD');
        $this->addSql('ALTER TABLE uso_de_stock DROP FOREIGN KEY FK_D018037F90119F0F');
        $this->addSql('DROP TABLE `admin`');
        $this->addSql('DROP TABLE banco');
        $this->addSql('DROP TABLE cestas');
        $this->addSql('DROP TABLE clientes');
        $this->addSql('DROP TABLE consultas');
        $this->addSql('DROP TABLE detallecesta');
        $this->addSql('DROP TABLE economicpresu');
        $this->addSql('DROP TABLE efectivo');
        $this->addSql('DROP TABLE email');
        $this->addSql('DROP TABLE encuesta');
        $this->addSql('DROP TABLE estadocestas');
        $this->addSql('DROP TABLE forecast');
        $this->addSql('DROP TABLE image');
        $this->addSql('DROP TABLE logs');
        $this->addSql('DROP TABLE mano_obra');
        $this->addSql('DROP TABLE pagos');
        $this->addSql('DROP TABLE post');
        $this->addSql('DROP TABLE presupuestos');
        $this->addSql('DROP TABLE presupuestos_lead');
        $this->addSql('DROP TABLE productos');
        $this->addSql('DROP TABLE stock_item');
        $this->addSql('DROP TABLE texto_mano_obra');
        $this->addSql('DROP TABLE tipo_mano_obra');
        $this->addSql('DROP TABLE tipoproducto');
        $this->addSql('DROP TABLE tiposmovimiento');
        $this->addSql('DROP TABLE uso_de_stock');
    }
}
