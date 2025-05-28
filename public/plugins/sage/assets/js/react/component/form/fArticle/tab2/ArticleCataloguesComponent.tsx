// https://react.dev/learn/add-react-to-an-existing-project#using-react-for-a-part-of-your-existing-page
import React, { ChangeEvent } from "react";
import { getTranslations } from "../../../../../functions/translations";
import { MetadataInterface } from "../../../../../interface/WordpressInterface";
import { FCatalogueInterface } from "../../../../../interface/FArticleInterface";
import { InputInterface } from "../../../../../interface/InputInterface";
import { getSageMetadata } from "../../../../../functions/getMetadata";
import Grid from "@mui/material/Grid";

let translations: any = getTranslations();

const articleMeta: MetadataInterface[] = JSON.parse(
  $("[data-sage-product]").attr("data-sage-product") ?? "null",
);

const fCatalogues: FCatalogueInterface[] = JSON.parse(
  $("[data-sage-fcatalogues]").attr("data-sage-fcatalogues") ?? "[]",
);

interface FormState {
  [key: `clNo${string}`]: InputInterface;
}

export const ArticleCataloguesComponent = React.forwardRef((props, ref) => {
  const nbNiveau = 4;
  const getDefaultValue = (): FormState => {
    const result: FormState = {};
    for (let i = 0; i < nbNiveau; i++) {
      let value = getSageMetadata(`clNo${i + 1}`, articleMeta).toString();
      if (value === "0") {
        value = "";
      }
      result[`clNo${i + 1}`] = {
        value: value,
      };
    }
    return result;
  };
  const [values, setValues] = React.useState<FormState>(getDefaultValue());

  const handleChangeSelect =
    (prop: keyof FormState) => (event: ChangeEvent<HTMLSelectElement>) => {
      setValues((v) => {
        let result: FormState = {
          ...v,
          [prop]: {
            ...v[prop],
            value: event.target.value as string,
            error: "",
          },
        };
        for (let i = nbNiveau - 1; i > Number(prop.replace("clNo", "")); i--) {
          result = {
            ...result,
            [`clNo${i}`]: {
              ...result[`clNo${i}`],
              value: "",
              error: "",
            },
          };
        }
        return result;
      });
    };

  React.useEffect(() => {}, []);

  return (
    <Grid container spacing={1}>
      {Array.from({ length: nbNiveau }, (_, i) => i).map((niveau) => {
        niveau = niveau + 1;
        const disabled = niveau > 1 && values[`clNo${niveau - 1}`].value === "";
        return (
          <Grid size={{ xs: 12, md: 3 }} key={niveau}>
            <select
              id={"_sage_clNo" + niveau}
              name={"_sage_clNo" + niveau}
              value={values[`clNo${niveau}`].value}
              onChange={handleChangeSelect(`clNo${niveau}`)}
              style={{ width: "100%" }}
              disabled={disabled}
            >
              <option value="">{translations.words.none}</option>
              {fCatalogues
                .filter((c) => {
                  let result = c.clNiveau === niveau - 1;
                  if (niveau > 1) {
                    result =
                      result &&
                      c.clNoParent.toString() ===
                        values[`clNo${niveau - 1}`].value;
                  }
                  return result;
                })
                .map((opt, index) => {
                  return (
                    <option key={index} value={opt.clNo.toString()}>
                      {opt.clIntitule}
                    </option>
                  );
                })}
            </select>
          </Grid>
        );
      })}
    </Grid>
  );
});
