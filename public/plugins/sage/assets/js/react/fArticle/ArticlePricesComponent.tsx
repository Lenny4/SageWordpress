// https://react.dev/learn/add-react-to-an-existing-project#using-react-for-a-part-of-your-existing-page
import React from "react";
import Accordion from "@mui/material/Accordion";
import AccordionSummary from "@mui/material/AccordionSummary";
import AccordionDetails from "@mui/material/AccordionDetails";
import Typography from "@mui/material/Typography";
import ArrowDropDownIcon from "@mui/icons-material/ArrowDropDown";
import { getTranslations } from "../../functions/translations";
import { MetadataInterface } from "../../interface/WordpressInterface";
import { FArticlePriceInterface } from "../../interface/FArticleInterface";
import { PriceComponent } from "../component/PriceComponent";

let translations: any = getTranslations();
const articleMeta: MetadataInterface[] = JSON.parse(
  $("[data-sage-product]").attr("data-sage-product") ?? "null",
);
const pCattarifs: any[] = Object.values(
  JSON.parse($("[data-sage-pcattarifs]").attr("data-sage-pcattarifs") ?? "[]"),
);
const pCatComptas: any[] = Object.values(
  JSON.parse($("[data-sage-pcatcomptas]").attr("data-sage-pcatcomptas") ?? "[]")
    .Ven,
);
const prices: FArticlePriceInterface[] = JSON.parse(
  articleMeta.find((item) => item.key === "_sage_prices").value,
);

const htTtcs = ["Ht", "Ttc"];
export const ArticlePricesComponent = () => {
  return (
    <Accordion defaultExpanded={false}>
      <AccordionSummary expandIcon={<ArrowDropDownIcon />}>
        <Typography component="span">{translations.words.seePrices}</Typography>
      </AccordionSummary>
      <AccordionDetails>
        <div style={{ overflow: "auto" }}>
          <table className="table-border table-padding">
            <thead>
              <tr>
                <td
                  rowSpan={3}
                  colSpan={2}
                  style={{ borderTop: "none", borderLeft: "none" }}
                ></td>
                <td colSpan={pCatComptas.length * 2} className="text-center">
                  {translations.words.accountingCategory}
                </td>
              </tr>
              <tr>
                {pCatComptas.map((pCatCompta, index) => (
                  <td colSpan={2} key={index} className="text-center">
                    {pCatCompta.label}
                  </td>
                ))}
              </tr>
              <tr>
                {pCatComptas.map((pCatCompta, index) => {
                  return htTtcs.map((htTtc, index2) => (
                    <td key={index + "_" + index2} className="text-center">
                      {translations.words[htTtc.toLowerCase()]}
                    </td>
                  ));
                })}
              </tr>
            </thead>
            <tbody>
              {pCattarifs.map((pCattarif, index) => (
                <tr key={index}>
                  {index === 0 && (
                    <td rowSpan={pCattarifs.length}>
                      {translations.words.fareCategory}
                    </td>
                  )}
                  <td>{pCattarif.ctIntitule}</td>
                  {pCatComptas.map((pCatCompta, index2) => {
                    return htTtcs.map((htTtc, index3) => {
                      const price = prices.find(
                        (p) =>
                          p.nCatCompta.cbIndice === pCatCompta.cbIndice &&
                          p.nCatTarif.cbIndice === pCattarif.cbIndice,
                      );
                      return (
                        <td key={index + "_" + index2 + "_" + index3}>
                          <PriceComponent
                            price={
                              // @ts-ignore
                              price["price" + htTtc]
                            }
                          />
                        </td>
                      );
                    });
                  })}
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </AccordionDetails>
    </Accordion>
  );
};
