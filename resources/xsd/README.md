# Esquemas XSD incluidos

Validación offline de los documentos DIAN (sin acceso a red).

| Carpeta / archivo | Origen | Licencia |
|---|---|---|
| `maindoc/UBL-*-2.1.xsd`, `common/UBL-*-2.1.xsd` | OASIS UBL 2.1 (copia de [phax/ph-ubl](https://github.com/phax/ph-ubl)) | OASIS, redistribución permitida |
| `common/CCTS_CCT_SchemaModule-2.1.xsd` | UN/CEFACT CCTS (vía [phax/ph-xsds](https://github.com/phax/ph-xsds)) | UN/CEFACT |
| `common/UBL-xmldsig-core-schema-2.1.xsd` | W3C XML Signature | W3C |
| `common/UBL-XAdES01903v1*.xsd` | ETSI XAdES 1.3.2 / 1.4.1 | ETSI |
| `common/DIAN_UBL_Structures.xsd` | DIAN (`sts:DianExtensions`) | Dominio público |

## Cambios locales

1. Los `xsd:import` sin `schemaLocation` se completaron con la ruta local, para que
   libxml resuelva todo desde disco.
2. `DIAN_UBL_Structures.xsd`: el atributo `@schemeID` de los identificadores DIAN
   (`sts:ProviderID`, `sts:AuthorizationProviderID`) aceptaba solo códigos de tipo
   de documento (11, 13, 31...). El Anexo Técnico V1.9 usa ese atributo para el
   DV, y así lo traen todos los documentos aprobados por la DIAN en
   `resources/fixtures`, por lo que se relajó a `[0-9]{1,2}`.

Todos los esquemas se prueban contra documentos reales aprobados por la DIAN
(`tests/Unit/Validator/XsdValidatorTest.php`).
