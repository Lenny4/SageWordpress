// https://react.dev/learn/add-react-to-an-existing-project#using-react-for-a-part-of-your-existing-page
import React, { useImperativeHandle, useRef } from "react";
import { getSageMetadata } from "../../../../../functions/getMetadata";
import { getTranslations } from "../../../../../functions/translations";
import { MetadataInterface } from "../../../../../interface/WordpressInterface";
import {
  FormInterface,
  FormValidInterface,
  TriggerFormContentChanged,
} from "../../../../../interface/InputInterface";
import {
  createFormContent,
  handleFormIsValid,
  transformOptionsObject,
} from "../../../../../functions/form";
import { DividerText } from "../../../DividerText";
import { ArRefInput } from "../inputs/ArRefInput";
import { ArPrixVenInput } from "../inputs/ArPrixVenInput";
import { ArticleCatTarifComponent } from "./ArticleCatTarifComponent";
import { ArticleFournisseursComponent } from "./ArticleFournisseursComponent";
import Grid from "@mui/material/Grid";
import { FormContentComponent } from "../../FormContentComponent";
import { FormInput } from "../../fields/FormInput";
import { FormSelect } from "../../fields/FormSelect";
import { numberValidator } from "../../../../../functions/validator";
import { TOKEN } from "../../../../../token";
import { ArDesignInput } from "../inputs/ArDesignInput";

let translations: any = getTranslations();
const articleMeta: MetadataInterface[] = JSON.parse(
  $(`[data-${TOKEN}-product]`).attr(`data-${TOKEN}-product`) ?? "[]",
);
const arRef = getSageMetadata("arRef", articleMeta);
const canEditArSuiviStock =
  getSageMetadata("canEditArSuiviStock", articleMeta, 1).toString() !== "0";
const isNew = !arRef;
const fFamilles: any[] = JSON.parse(
  $(`[data-${TOKEN}-ffamilles]`).attr(`data-${TOKEN}-ffamilles`) ?? "[]",
);
const pUnites: any[] = JSON.parse(
  $(`[data-${TOKEN}-punites]`).attr(`data-${TOKEN}-punites`) ?? "[]",
);

export const ArticleTab1Component = React.forwardRef((props, ref) => {
  const arRefRef = useRef<any>(null);
  const arDesignRef = useRef<any>(null);

  const [arCoef, setArCoef] = React.useState<string>(
    getSageMetadata("arCoef", articleMeta, "0").toString(),
  );
  const onArCoefChanged: TriggerFormContentChanged = (name, newValue) => {
    setArCoef(newValue);
  };

  const [arPrixAch, setArPrixAch] = React.useState<string>(
    getSageMetadata("arPrixAch", articleMeta, "0").toString(),
  );
  const onArPrixAchChanged: TriggerFormContentChanged = (name, newValue) => {
    setArPrixAch(newValue);
  };

  const [arType, setArType] = React.useState<string>(
    getSageMetadata("arType", articleMeta, "0").toString(),
  );
  const onArTypeChanged: TriggerFormContentChanged = (name, newValue) => {
    setArType(newValue);
  };

  const getForm = (): FormInterface => {
    return {
      content: createFormContent({
        props: {
          container: true,
          spacing: 1,
          sx: { p: 1 },
        },
        children: [
          {
            props: {
              size: { xs: 12 },
            },
            Dom: (
              <DividerText
                textAlign="left"
                text={<h2>{translations.words.identification}</h2>}
              />
            ),
          },
          {
            Dom: (
              <ArRefInput isNew={isNew} defaultValue={arRef} ref={arRefRef} />
            ),
          },
          {
            fields: [
              {
                name: "arType",
                DomField: FormSelect,
                readOnly: !isNew,
                tooltip: translations.sentences.arType,
                triggerFormContentChanged: onArTypeChanged,
                options: transformOptionsObject(
                  translations.fArticles.arType.values,
                ).map((v) => {
                  // todo prendre l'info dans $this->importCondition
                  return {
                    ...v,
                    disabled: !["0", "1"].includes(v.value),
                  };
                }),
                initValues: {
                  value: arType,
                },
              },
            ],
          },
          {
            props: {
              size: { xs: 12 },
            },
            Dom: (
              <ArDesignInput
                defaultValue={getSageMetadata("arDesign", articleMeta)}
                ref={arDesignRef}
              />
            ),
          },
          {
            fields: [
              {
                name: "faCodeFamille",
                DomField: FormSelect,
                options: fFamilles.map((f) => {
                  return {
                    value: f.faCodeFamille,
                    label: f.faCodeFamille + " " + f.faIntitule,
                  };
                }),
                initValues: {
                  value: getSageMetadata("faCodeFamille", articleMeta),
                },
              },
              {
                name: "arSuiviStock",
                DomField: FormSelect,
                readOnly: !canEditArSuiviStock,
                options: transformOptionsObject(
                  translations.fArticles.arSuiviStock.values,
                ),
                initValues: {
                  value: getSageMetadata("arSuiviStock", articleMeta),
                },
              },
            ],
          },
          {
            fields: [
              {
                name: "arNomencl",
                DomField: FormSelect,
                // todo prendre l'info dans $this->importCondition
                readOnly: true, // pour l'instant
                tooltip: translations.sentences.arNomencl,
                options: transformOptionsObject(
                  translations.fArticles.arNomencl.values,
                ),
                initValues: {
                  value: getSageMetadata("arNomencl", articleMeta),
                },
              },
              {
                name: "arCondition",
                readOnly: true,
                cannotBeChangeOnWebsite: true,
                DomField: FormSelect,
                options: transformOptionsObject(
                  translations.fArticles.arCondition.values,
                ),
                initValues: {
                  value: getSageMetadata("arCondition", articleMeta),
                },
              },
            ],
          },
          {
            props: {
              size: { xs: 12 },
            },
            Dom: (
              <DividerText
                textAlign="left"
                text={<h2>{translations.words.tarif}</h2>}
              />
            ),
          },
          {
            fields: [
              {
                name: "arPrixAch",
                DomField: FormInput,
                type: "number",
                triggerFormContentChanged: onArPrixAchChanged,
                initValues: {
                  value: arPrixAch,
                  validator: {
                    functionName: numberValidator,
                    params: {
                      canBeEmpty: true,
                    },
                  },
                },
              },
              {
                name: "arCoef",
                DomField: FormInput,
                triggerFormContentChanged: onArCoefChanged,
                type: "number",
                initValues: {
                  value: arCoef,
                  validator: {
                    functionName: numberValidator,
                    params: {
                      canBeEmpty: true,
                    },
                  },
                },
              },
            ],
            children: [
              {
                props: {
                  container: true,
                },
                children: [
                  {
                    props: {
                      size: { xs: 12, md: 8 },
                    },
                    Dom: (
                      <ArPrixVenInput
                        defaultValue={getSageMetadata(
                          "arPrixVen",
                          articleMeta,
                          "0",
                        ).toString()}
                        arCoef={arCoef}
                        arPrixAch={arPrixAch}
                        ref={React.createRef()}
                      />
                    ),
                  },
                  {
                    props: {
                      size: { xs: 12, md: 4 },
                      sx: {
                        paddingTop: "19px",
                      },
                    },
                    fields: [
                      {
                        name: "arPrixTtc",
                        DomField: FormSelect,
                        hideLabel: true,
                        options: transformOptionsObject(
                          translations.fArticles.arPrixTtc.values,
                        ),
                        initValues: {
                          value: getSageMetadata("arPrixTtc", articleMeta),
                        },
                      },
                    ],
                  },
                ],
              },
            ],
          },
          {
            fields: [
              {
                name: "arPunet",
                DomField: FormInput,
                type: "number",
                initValues: {
                  value: getSageMetadata("arPunet", articleMeta),
                  validator: {
                    functionName: numberValidator,
                    params: {
                      canBeEmpty: true,
                    },
                  },
                },
              },
              {
                name: "arCoutStd",
                DomField: FormInput,
                type: "number",
                initValues: {
                  value: getSageMetadata("arCoutStd", articleMeta),
                  validator: {
                    functionName: numberValidator,
                    params: {
                      canBeEmpty: true,
                    },
                  },
                },
              },
              {
                name: "arUniteVen",
                DomField: FormSelect,
                readOnly: true,
                cannotBeChangeOnWebsite: true,
                options: pUnites.map((f) => {
                  return {
                    value: f.cbIndice,
                    label: f.uIntitule,
                  };
                }),
                initValues: {
                  value: getSageMetadata("arUniteVen", articleMeta),
                },
              },
            ],
          },
          {
            props: {
              size: { xs: 12 },
            },
            tabs: {
              id: "tab1-sub",
              tabs: [
                {
                  label: translations.words.nCatTarif,
                  Component: ArticleCatTarifComponent,
                  props: { arPrixAch: arPrixAch, arCoef: arCoef },
                },
                {
                  label: translations.words.suppliers,
                  Component: ArticleFournisseursComponent,
                },
              ].map(({ label, Component, props }) => {
                const ref = React.createRef();
                return {
                  label,
                  dom: <Component ref={ref} {...props} />,
                  ref,
                };
              }),
            },
          },
        ],
      }),
    };
  };
  const [form, setForm] = React.useState<FormInterface>(getForm());

  useImperativeHandle(ref, () => ({
    async isValid(): Promise<FormValidInterface> {
      return await handleFormIsValid(form.content);
    },
  }));

  React.useEffect(() => {
    setForm(getForm());
  }, [arCoef, arPrixAch, arType]); // eslint-disable-line react-hooks/exhaustive-deps

  return (
    <Grid container>
      <Grid size={{ xs: 12 }}>
        <FormContentComponent content={form.content} transPrefix="fArticles" />
      </Grid>
      <input
        type="hidden"
        name="product-type"
        value={arType === "1" ? "variable" : "simple"}
      />
    </Grid>
  );
});
