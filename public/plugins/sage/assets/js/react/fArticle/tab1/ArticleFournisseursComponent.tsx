// https://react.dev/learn/add-react-to-an-existing-project#using-react-for-a-part-of-your-existing-page
import React, { ChangeEvent } from "react";
import {
  FormInterface,
  InputInterface,
  TableLineInterface,
} from "../../../interface/InputInterface";
import {
  getFlatFields,
  handleChangeInputGeneric,
  handleChangeSelectGeneric,
} from "../../../functions/form";
import { FormContentComponent } from "../../component/form/FormContentComponent";
import {
  getListObjectSageMetadata,
  getSageMetadata,
} from "../../../functions/getMetadata";
import { getTranslations } from "../../../functions/translations";
import { FormInput } from "../../component/form/FormInput";
import { FArtfournisseInterface } from "../../../interface/FArticleInterface";
import { MetadataInterface } from "../../../interface/WordpressInterface";
import { AfPrincipalInput } from "../../component/form/fArticle/AfPrincipalInput";

let translations: any = getTranslations();

const articleMeta: MetadataInterface[] = JSON.parse(
  $("[data-sage-product]").attr("data-sage-product") ?? "null",
);

export const ArticleFournisseursComponent = React.forwardRef((props, ref) => {
  const handleChange =
    (prop: keyof FormState) => (event: React.ChangeEvent<HTMLInputElement>) => {
      handleChangeInputGeneric(event, prop, setValues);
    };

  const handleChangeSelect =
    (prop: keyof FormState) => (event: ChangeEvent<HTMLSelectElement>) => {
      handleChangeSelectGeneric(event, prop, setValues);
    };

  const prefix = "fArtfournisses";

  const [selectedCtNum, setSelectedCtNum] = React.useState<string>(() => {
    const fArtfournisses: FArtfournisseInterface[] = getListObjectSageMetadata(
      prefix,
      articleMeta,
      "ctNum",
    );
    return (
      fArtfournisses.find((x) => x.afPrincipal.toString() === "1")?.ctNum ?? ""
    );
  });
  const [afPrincipalRefs, setAfPrincipalRefs] = React.useState<any[]>([]);

  const [form] = React.useState<FormInterface>(() => {
    const fArtfournisses: FArtfournisseInterface[] = getListObjectSageMetadata(
      prefix,
      articleMeta,
      "ctNum",
    );

    return {
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
              table: {
                headers: [
                  translations.words.supplier,
                  translations.words.main,
                  translations.words.supplierRef,
                  translations.words.buyPrice,
                ],
                lines: fArtfournisses.map(
                  (fArtclient): TableLineInterface[] => {
                    const refAfPrincipal = React.createRef();
                    setAfPrincipalRefs((x) => [...x, refAfPrincipal]);

                    return [
                      {
                        Dom: <>{fArtclient.ctNum}</>,
                      },
                      {
                        Dom: (
                          <AfPrincipalInput
                            defaultCtNum={selectedCtNum}
                            ctNum={fArtclient.ctNum}
                            setSelectedCtNumParent={setSelectedCtNum}
                            ref={refAfPrincipal}
                          />
                        ),
                      },
                      {
                        field: {
                          name:
                            prefix + "[" + fArtclient.ctNum + "].afRefFourniss",
                          DomField: FormInput,
                          hideLabel: true,
                        },
                      },
                      {
                        field: {
                          name: prefix + "[" + fArtclient.ctNum + "].afPrixAch",
                          DomField: FormInput,
                          hideLabel: true,
                        },
                      },
                    ];
                  },
                ),
              },
            },
          ],
        },
      ],
    };
  });

  const [flatFields] = React.useState(getFlatFields(form));
  const [fieldNames] = React.useState(flatFields.map((f) => f.name));

  type FieldKeys = (typeof fieldNames)[number];

  interface FormState extends Record<FieldKeys, InputInterface> {}

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
      ...fieldValues,
    };
  };
  const [values, setValues] = React.useState<FormState>(getDefaultValue());

  React.useEffect(() => {
    for (const afPrincipalRef of afPrincipalRefs) {
      afPrincipalRef.current.onParentFormChange(selectedCtNum);
    }
  }, [selectedCtNum]);

  return (
    <FormContentComponent
      content={form.content}
      values={values}
      handleChange={handleChange}
      transPrefix="fArticles"
      handleChangeSelect={handleChangeSelect}
    />
  );
});
