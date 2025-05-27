// https://react.dev/learn/add-react-to-an-existing-project#using-react-for-a-part-of-your-existing-page
import React, { useImperativeHandle, useRef } from "react";
import { getTranslations } from "../../../functions/translations";
import {
  FormContentInterface,
  FormInterface,
  TriggerFormContentChanged,
} from "../../../interface/InputInterface";
import { getSageMetadata } from "../../../functions/getMetadata";
import { FormInput } from "../../component/form/FormInput";
import {
  getDomsToSetParentFormData,
  stringValidator,
  transformOptionsObject,
} from "../../../functions/form";
import { FormContentComponent } from "../../component/form/FormContentComponent";
import { DividerText } from "../../component/DividerText";
import { FormSelect } from "../../component/form/FormSelect";
import Grid from "@mui/material/Grid";
import { ArticleCatTarifComponent } from "./ArticleCatTarifComponent";
import { ArticleFournisseursComponent } from "./ArticleFournisseursComponent";
import { ArRefInput } from "../../component/form/fArticle/ArRefInput";
import { MetadataInterface } from "../../../interface/WordpressInterface";

let translations: any = getTranslations();
const articleMeta: MetadataInterface[] = JSON.parse(
  $("[data-sage-product]").attr("data-sage-product") ?? "null",
);
const arRef = getSageMetadata("arRef", articleMeta);
const canEditArSuiviStock =
  (getSageMetadata("canEditArSuiviStock", articleMeta) ?? 1).toString() !== "0";
const isNew = !arRef;
const fFamilles: any[] = JSON.parse(
  $("[data-sage-ffamilles]").attr("data-sage-ffamilles") ?? "[]",
);
const pUnites: any[] = JSON.parse(
  $("[data-sage-punites]").attr("data-sage-punites") ?? "[]",
);

export const ArticleTab1Component = React.forwardRef((props, ref) => {
  const arRefRef = useRef<any>(null);
  const [arType, setArType] = React.useState<string>(
    (getSageMetadata("arType", articleMeta) ?? "0").toString(),
  );

  const onArPrixAchChanged: TriggerFormContentChanged = (name, newValue) => {
    const doms = getDomsToSetParentFormData(form.content);
    for (const dom of doms) {
      if (dom.ref.current?.onArPrixAchChanged) {
        dom.ref.current.onArPrixAchChanged(newValue);
      }
    }
  };

  const onArTypeChanged: TriggerFormContentChanged = (name, newValue) => {
    setArType(newValue);
  };

  const [form] = React.useState<FormInterface>(() => {
    const formContent: FormContentInterface[] = [
      {
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
                  return {
                    ...v,
                    disabled: !["0", "1"].includes(v.value),
                  };
                }),
                initValues: {
                  value: getSageMetadata("arType", articleMeta) ?? "",
                },
              },
            ],
          },
          {
            props: {
              size: { xs: 12 },
            },
            fields: [
              {
                name: "arDesign",
                DomField: FormInput,
                validator: {
                  functionName: stringValidator,
                  params: {
                    maxLength: 69,
                  },
                },
                initValues: {
                  value: getSageMetadata("arDesign", articleMeta) ?? "",
                },
              },
            ],
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
                  value: getSageMetadata("faCodeFamille", articleMeta) ?? "",
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
                  value: getSageMetadata("arSuiviStock", articleMeta) ?? "",
                },
              },
            ],
          },
          {
            fields: [
              {
                name: "arNomencl",
                DomField: FormSelect,
                readOnly: true, // pour l'instant
                tooltip: translations.sentences.arNomencl,
                options: transformOptionsObject(
                  translations.fArticles.arNomencl.values,
                ),
                initValues: {
                  value: getSageMetadata("arNomencl", articleMeta) ?? "",
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
                  value: getSageMetadata("arCondition", articleMeta) ?? "",
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
                  value: getSageMetadata("arPrixAch", articleMeta) ?? "",
                },
              },
              {
                name: "arCoef",
                DomField: FormInput,
                type: "number",
                initValues: {
                  value: getSageMetadata("arCoef", articleMeta) ?? "",
                },
              },
            ],
            children: [
              {
                props: {
                  container: true,
                  sx: {
                    alignItems: "flex-end",
                  },
                },
                children: [
                  {
                    props: {
                      size: { xs: 12, md: 8 },
                    },
                    fields: [
                      {
                        name: "arPrixVen",
                        DomField: FormInput,
                        type: "number",
                        initValues: {
                          value:
                            getSageMetadata("arPrixVen", articleMeta) ?? "",
                        },
                      },
                    ],
                  },
                  {
                    props: {
                      size: { xs: 12, md: 4 },
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
                          value:
                            getSageMetadata("arPrixTtc", articleMeta) ?? "",
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
                  value: getSageMetadata("arPunet", articleMeta) ?? "",
                },
              },
              {
                name: "arCoutStd",
                DomField: FormInput,
                type: "number",
                initValues: {
                  value: getSageMetadata("arCoutStd", articleMeta) ?? "",
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
                  value: getSageMetadata("arUniteVen", articleMeta) ?? "",
                },
              },
            ],
          },
          {
            props: {
              size: { xs: 12 },
            },
            tabs: [
              {
                label: translations.words.nCatTarif,
                Component: ArticleCatTarifComponent,
              },
              {
                label: translations.words.suppliers,
                Component: ArticleFournisseursComponent,
              },
            ].map(({ label, Component }) => {
              const ref = React.createRef();
              return {
                label,
                dom: <Component ref={ref} />,
                ref,
              };
            }),
          },
        ],
      },
    ];
    return {
      content: formContent,
    };
  });

  const handleIsValid = () => {
    console.log("handleIsValid");
    // let isValid = isValidGeneric(values, setValues);
    // isValid = isValid && arRefRef.current.isValid();
    return false;
  };

  useImperativeHandle(ref, () => ({
    isValid(): boolean {
      return handleIsValid();
    },
    getForm() {
      return form;
    },
  }));

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
