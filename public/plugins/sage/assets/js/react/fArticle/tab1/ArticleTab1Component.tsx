// https://react.dev/learn/add-react-to-an-existing-project#using-react-for-a-part-of-your-existing-page
import React, { ChangeEvent, useImperativeHandle, useRef } from "react";
import { getTranslations } from "../../../functions/translations";
import {
  FormInterface,
  InputInterface,
} from "../../../interface/InputInterface";
import { getSageMetadata } from "../../../functions/getMetadata";
import { FormInput } from "../../component/form/FormInput";
import {
  getFlatFields,
  handleChangeInputGeneric,
  handleChangeSelectGeneric,
  isValidGeneric,
  stringValidator,
  transformOptionsObject,
} from "../../../functions/form";
import { FormContentComponent } from "../../component/form/FormContentComponent";
import { DividerText } from "../../component/DividerText";
import { FormSelect } from "../../component/form/FormSelect";
import { ArRefInput } from "../../component/form/ArRefInput";
import { TabInterface } from "../../../interface/TabInterface";
import { TabsComponent } from "../../component/tab/TabsComponent";
import Grid from "@mui/material/Grid";
import { ArticleCatTarifComponent } from "./ArticleCatTarifComponent";
import { ArticleFournisseursComponent } from "./ArticleFournisseursComponent";

let translations: any = getTranslations();
const articleMeta = JSON.parse(
  $("[data-sage-product]").attr("data-sage-product") ?? "null",
);
const arRef = getSageMetadata("arRef", articleMeta);
const canEditArSuiviStock =
  getSageMetadata("canEditArSuiviStock", articleMeta) ?? 1;
const isNew = !arRef;
const fFamilles: any[] = JSON.parse(
  $("[data-sage-ffamilles]").attr("data-sage-ffamilles") ?? "[]",
);
const pUnites: any[] = JSON.parse(
  $("[data-sage-punites]").attr("data-sage-punites") ?? "[]",
);

export const ArticleTab1Component = React.forwardRef((props, ref) => {
  const handleChange =
    (prop: keyof FormState) => (event: React.ChangeEvent<HTMLInputElement>) => {
      handleChangeInputGeneric(event, prop, setValues);
    };

  const handleChangeSelect =
    (prop: keyof FormState) => (event: ChangeEvent<HTMLSelectElement>) => {
      handleChangeSelectGeneric(event, prop, setValues);
    };
  const arRefRef = useRef<any>(null);

  const [tabs] = React.useState<TabInterface[]>(() => {
    return [
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
    });
  });

  const [form] = React.useState<FormInterface>({
    content: [
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
                options: transformOptionsObject(
                  translations.fArticles.arType.values,
                ).map((v) => {
                  return {
                    ...v,
                    disabled: !["0", "1"].includes(v.value),
                  };
                }),
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
              },
              {
                name: "arSuiviStock",
                DomField: FormSelect,
                readOnly: canEditArSuiviStock.toString() === "0",
                options: transformOptionsObject(
                  translations.fArticles.arSuiviStock.values,
                ),
              },
            ],
          },
          {
            fields: [
              {
                name: "arNomencl",
                DomField: FormSelect,
                readOnly: true, // pour l'instant
                options: transformOptionsObject(
                  translations.fArticles.arNomencl.values,
                ),
              },
              {
                name: "arCondition",
                readOnly: true,
                cannotBeChangeOnWebsite: true,
                DomField: FormSelect,
                options: transformOptionsObject(
                  translations.fArticles.arCondition.values,
                ),
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
              { name: "arPrixAch", DomField: FormInput, type: "number" },
              { name: "arCoef", DomField: FormInput, type: "number" },
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
                      },
                    ],
                  },
                ],
              },
            ],
          },
          {
            fields: [
              { name: "arPunet", DomField: FormInput, type: "number" },
              { name: "arCoutStd", DomField: FormInput, type: "number" },
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
              },
            ],
          },
        ],
      },
    ],
  });
  const [flatFields] = React.useState(getFlatFields(form));
  const [fieldNames] = React.useState(flatFields.map((f) => f.name));

  type FieldKeys = (typeof fieldNames)[number];

  interface FormState extends Record<FieldKeys, InputInterface> {
    isNew: InputInterface;
  }

  const getDefaultValue = (): FormState => {
    const fieldValues = flatFields.reduce(
      (acc, field) => {
        acc[field.name] = {
          value: getSageMetadata(field.name, articleMeta) ?? "",
          validator: field.validator,
        };
        return acc;
      },
      {} as Record<(typeof fieldNames)[number], InputInterface>,
    );

    return {
      isNew: { value: !arRef },
      ...fieldValues,
    };
  };
  const [values, setValues] = React.useState<FormState>(getDefaultValue());

  const handleDisabledFields = () => {
    const disabledArCondition = ["1", "5"].includes(
      values.arSuiviStock.value.toString(),
    );

    const changeDisabledArCondition =
      disabledArCondition !== !!values.arCondition.readOnly;

    if (changeDisabledArCondition) {
      setValues((v) => ({
        ...v,
        arCondition: {
          ...v.arCondition,
          readOnly: disabledArCondition,
          value: disabledArCondition ? "0" : v.arCondition.value,
        },
      }));
    }
  };

  const handleIsValid = () => {
    let isValid = isValidGeneric(values, setValues);
    isValid = isValid && arRefRef.current.isValid();
    return isValid;
  };

  useImperativeHandle(ref, () => ({
    isValid(): boolean {
      return handleIsValid();
    },
  }));

  React.useEffect(() => {
    handleDisabledFields();
    for (const tab of tabs) {
      tab.ref.current?.onParentFormChange(values);
    }
  }, [values]);

  return (
    <Grid container>
      <Grid size={{ xs: 12 }}>
        <FormContentComponent
          content={form.content}
          values={values}
          handleChange={handleChange}
          handleChangeSelect={handleChangeSelect}
          transPrefix="fArticles"
        />
      </Grid>
      <input
        type="hidden"
        name="product-type"
        value={values.arType.value === "1" ? "variable" : "simple"}
      />
      <Grid size={{ xs: 12 }}>
        <TabsComponent tabs={tabs} />
      </Grid>
    </Grid>
  );
});
